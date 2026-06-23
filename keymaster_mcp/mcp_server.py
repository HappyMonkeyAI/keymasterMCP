from typing import Optional
from mcp.server.fastmcp import FastMCP
import asyncio
import json
import secrets
from pathlib import Path

from keymaster_mcp.vault import Vault
from keymaster_mcp.database import Database
from keymaster_mcp.config import get_settings


mcp = FastMCP("keymaster-vault")


# ---------------------------------------------------------------------------
# Existing tools (kept for compatibility)
# ---------------------------------------------------------------------------

@mcp.tool()
def list_services() -> list[dict]:
    """List configured services in the vault (arbitrary services supported)."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    services = vault.list_services()
    return [{"name": s, "configured": True} for s in services]


@mcp.tool()
def get_service_status(service: str) -> dict:
    """Get the status of a specific service."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    return {
        "name": service,
        "configured": vault.has_key(service),
    }


async def list_clients_async() -> list[dict]:
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    return await db.list_clients()


@mcp.tool()
def list_clients() -> list[dict]:
    import asyncio
    return asyncio.run(list_clients_async())


@mcp.tool()
def get_vault_info() -> dict:
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    return {
        "vault_path": settings.keymaster_vault_path,
        "configured_services": vault.list_services(),
    }


# ---------------------------------------------------------------------------
# New MCP-first ingestion pipeline
# ---------------------------------------------------------------------------

KEYMASTER_DIR = ".keymaster"
BOOTSTRAP_KEY_FILE = "project.key"


def _get_local_project_context() -> Optional[dict]:
    """Discover local .keymaster/ bootstrap key for the current working directory."""
    cwd = Path.cwd()
    key_dir = cwd / KEYMASTER_DIR
    key_file = key_dir / BOOTSTRAP_KEY_FILE
    if key_file.exists():
        try:
            data = json.loads(key_file.read_text())
            return data
        except Exception:
            return None
    return None


def _write_local_bootstrap_key(project_slug: str, client_id: str, client_secret: str) -> Path:
    """Write a local bootstrap key file (0600)."""
    cwd = Path.cwd()
    key_dir = cwd / KEYMASTER_DIR
    key_dir.mkdir(parents=True, exist_ok=True)
    key_file = key_dir / BOOTSTRAP_KEY_FILE

    data = {
        "project_slug": project_slug,
        "client_id": client_id,
        "client_secret": client_secret,
        "created_at": __import__("datetime").datetime.utcnow().isoformat(),
    }
    key_file.write_text(json.dumps(data, indent=2))
    key_file.chmod(0o600)
    return key_file


@mcp.tool()
async def keymaster_init_project(
    name: str,
    slug: Optional[str] = None,
    description: Optional[str] = None,
    project_type: str = "secrets",
    ip_whitelist: Optional[list[str]] = None,
) -> dict:
    """
    Initialize a new project and create a local .keymaster/ bootstrap key.
    This is the primary ingestion entry point for agents.
    """
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()

    # Create project
    project = await db.create_project(name, description, project_type)
    project_slug = slug or project["slug"]

    # Create bootstrap client
    client_id = f"project-{project_slug}"
    client_id, client_secret = await db.create_client(
        client_id=client_id,
        name=f"Project {name} Bootstrap",
        role="project-bootstrap",
    )

    # Write local key file
    key_path = _write_local_bootstrap_key(project_slug, client_id, client_secret)

    # Add IP whitelist if provided
    if ip_whitelist:
        for ip in ip_whitelist:
            await db.add_project_ip(project["id"], ip)

    return {
        "success": True,
        "project_id": project["id"],
        "slug": project_slug,
        "local_key_path": str(key_path),
        "client_id": client_id,
        "message": "Bootstrap key written. Add to .gitignore and protect with nginx deny rule.",
    }


@mcp.tool()
async def keymaster_register_key(
    project_slug: str,
    service: str,
    key: str,
    display_name: Optional[str] = None,
) -> dict:
    """
    Register an API credential for a service into a project.
    Primary ingestion path for agents (MCP-first).
    """
    settings = get_settings()
    db = Database(settings.database_path)
    vault = Vault(settings.keymaster_vault_path)
    await db.init()

    # Find project
    projects = await db.list_projects()
    project = next((p for p in projects if p["slug"] == project_slug), None)
    if not project:
        return {"success": False, "error": f"Project '{project_slug}' not found"}

    # Store in vault
    vault.set_key(service, key)

    # Link to project
    await db.add_project_credential(project["id"], service)

    # Register in credential registry if new
    await db.register_credential(service, display_name or service)

    return {
        "success": True,
        "project_slug": project_slug,
        "service": service,
        "message": f"Key for '{service}' registered to project '{project_slug}'",
    }


@mcp.tool()
async def keymaster_list_project_keys(project_slug: str) -> dict:
    """List registered services for a project (metadata only)."""
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()

    projects = await db.list_projects()
    project = next((p for p in projects if p["slug"] == project_slug), None)
    if not project:
        return {"success": False, "error": f"Project not found: {project_slug}"}

    creds = await db.get_project_credentials(project["id"])
    return {"success": True, "project_slug": project_slug, "services": creds}