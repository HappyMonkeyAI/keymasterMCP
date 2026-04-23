from typing import Optional
from mcp.server.fastmcp import FastMCP

from keymaster_mcp.vault import Vault
from keymaster_mcp.database import Database
from keymaster_mcp.config import get_settings


mcp = FastMCP("keymaster-vault")


@mcp.tool()
def list_services() -> list[dict]:
    """List all available services and their configuration status."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    return [
        {"name": service, "configured": vault.has_key(service)}
        for service in Vault.SUPPORTED_SERVICES
    ]


@mcp.tool()
def get_service_status(service: str) -> dict:
    """Get the status of a specific service."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    if service not in Vault.SUPPORTED_SERVICES:
        return {"error": f"Unsupported service: {service}"}
    
    return {
        "name": service,
        "configured": vault.has_key(service),
    }


async def list_clients_async() -> list[dict]:
    """List all registered clients (async)."""
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    return await db.list_clients()


@mcp.tool()
def list_clients() -> list[dict]:
    """List all registered clients."""
    import asyncio
    return asyncio.run(list_clients_async())


@mcp.tool()
def get_vault_info() -> dict:
    """Get general vault information."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    return {
        "vault_path": settings.keymaster_vault_path,
        "supported_services": Vault.SUPPORTED_SERVICES,
        "configured_services": vault.list_services(),
    }
