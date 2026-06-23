from typing import Optional, Any
from mcp.server.fastmcp import FastMCP
import asyncio
import json
from pathlib import Path
import httpx

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


# ---------------------------------------------------------------------------
# Consumption layer (agents use these instead of .env)
# ---------------------------------------------------------------------------

@mcp.tool()
def keymaster_get_current_project() -> dict:
    """
    Resolve the current project from the local .keymaster/ directory.
    Agents call this first to know which project context they are in.
    """
    context = _get_local_project_context()
    if not context:
        return {"success": False, "error": "No .keymaster/ project context found in current directory"}
    return {"success": True, **context}


@mcp.tool()
async def keymaster_get_key(service: str, project_slug: Optional[str] = None) -> dict:
    """
    Check if a key exists for a service in the current project context.
    NEVER returns the raw secret. Agents should use proxy tools or
    the MCP server to make authenticated requests instead of reading .env.
    """
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    db = Database(settings.database_path)
    await db.init()

    if not project_slug:
        context = _get_local_project_context()
        if not context:
            return {"success": False, "error": "No project context and no project_slug provided"}
        project_slug = context.get("project_slug")

    # Verify the project has this service assigned
    projects = await db.list_projects()
    project = next((p for p in projects if p["slug"] == project_slug), None)
    if not project:
        return {"success": False, "error": f"Project '{project_slug}' not found"}

    creds = await db.get_project_credentials(project["id"])
    has_service = any(c["service"] == service for c in creds)

    if not has_service:
        return {"success": False, "error": f"Service '{service}' not assigned to project '{project_slug}'"}

    exists = vault.has_key(service)
    return {
        "success": True,
        "service": service,
        "project_slug": project_slug,
        "exists": exists,
        "message": "Use keymaster_proxy_request or MCP-native clients to consume this key securely."
    }


# ---------------------------------------------------------------------------
# Proxy layer (recommended way for agents to use keys)
# ---------------------------------------------------------------------------

@mcp.tool()
async def keymaster_proxy_request(
    service: str,
    url: str,
    method: str = "GET",
    headers: Optional[dict] = None,
    json_body: Optional[Any] = None,
    project_slug: Optional[str] = None,
) -> dict:
    """
    Make an authenticated API request using a stored key.
    The raw key is never exposed to the calling agent.
    This is the primary recommended consumption method.
    """
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    db = Database(settings.database_path)
    await db.init()

    if not project_slug:
        context = _get_local_project_context()
        if not context:
            return {"success": False, "error": "No project context found"}
        project_slug = context.get("project_slug")

    # Verify project + service
    projects = await db.list_projects()
    project = next((p for p in projects if p["slug"] == project_slug), None)
    if not project:
        return {"success": False, "error": f"Project '{project_slug}' not found"}

    creds = await db.get_project_credentials(project["id"])
    has_service = any(c["service"] == service for c in creds)
    if not has_service:
        return {"success": False, "error": f"Service '{service}' not assigned to project"}

    api_key = vault.get_key(service)
    if not api_key:
        return {"success": False, "error": f"No key stored for service '{service}'"}

    # Prepare headers
    request_headers = headers or {}
    # Common patterns for different providers
    if service in ("openai", "anthropic"):
        request_headers["Authorization"] = f"Bearer {api_key}"
    else:
        request_headers["Authorization"] = f"Bearer {api_key}"

    try:
        async with httpx.AsyncClient() as client:
            resp = await client.request(
                method=method.upper(),
                url=url,
                headers=request_headers,
                json=json_body,
                timeout=30.0,
            )
            return {
                "success": True,
                "status_code": resp.status_code,
                "body": resp.json() if resp.headers.get("content-type", "").startswith("application/json") else resp.text,
            }
    except Exception as e:
        return {"success": False, "error": str(e)}