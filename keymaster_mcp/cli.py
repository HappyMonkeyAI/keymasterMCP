import asyncio
import json

import click
from rich.console import Console
from rich.table import Table

from keymaster_mcp.vault import Vault
from keymaster_mcp.database import Database
from keymaster_mcp.config import get_settings


console = Console()

SERVICE_ENV_MAP = {
    "openai": "OPENAI_API_KEY",
    "anthropic": "ANTHROPIC_API_KEY",
    "github": "GITHUB_TOKEN",
}


@click.group()
def cli():
    """Keymaster MCP CLI - Manage API keys and clients."""
    pass


@cli.group()
def vault():
    """Manage the encrypted vault."""
    pass


@vault.command("list")
def vault_list():
    """List all configured services."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    table = Table(title="Configured Services")
    table.add_column("Service", style="cyan")
    table.add_column("Configured", style="green")
    
    for service in Vault.SUPPORTED_SERVICES:
        configured = vault.has_key(service)
        table.add_row(service, "✓" if configured else "✗")
    
    console.print(table)


@vault.command("add")
@click.argument("service")
@click.argument("api_key")
def vault_add(service: str, api_key: str):
    """Add an API key for a service."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    try:
        vault.set_key(service, api_key)
        console.print(f"[green]✓[/green] Added key for {service}")
    except ValueError as e:
        console.print(f"[red]Error:[/red] {e}")


@vault.command("remove")
@click.argument("service")
def vault_remove(service: str):
    """Remove an API key for a service."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    if vault.delete_key(service):
        console.print(f"[green]✓[/green] Removed key for {service}")
    else:
        console.print(f"[yellow]No key found for {service}[/yellow]")


@vault.command("rotate")
@click.argument("service")
@click.argument("new_api_key")
def vault_rotate(service: str, new_api_key: str):
    """Rotate an API key for a service."""
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    vault.rotate_key(service, new_api_key)
    console.print(f"[green]✓[/green] Rotated key for {service}")


@cli.group()
def clients():
    """Manage client credentials."""
    pass


@clients.command("list")
async def clients_list():
    """List all registered clients."""
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    
    clients = await db.list_clients()
    
    if not clients:
        console.print("[yellow]No clients registered[/yellow]")
        return
    
    table = Table(title="Registered Clients")
    table.add_column("Client ID", style="cyan")
    table.add_column("Name", style="white")
    table.add_column("Created", style="gray")
    table.add_column("Last Used", style="gray")
    
    for client in clients:
        table.add_row(
            client["client_id"],
            client.get("name", "-"),
            client.get("created_at", "-"),
            client.get("last_used_at", "Never"),
        )
    
    console.print(table)


@clients.command("create")
@click.argument("client_id")
@click.option("--name", "-n", help="Client name")
async def clients_create(client_id: str, name: str | None):
    """Create a new client credential."""
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    
    client_id, client_secret = await db.create_client(client_id, name)
    
    console.print(f"[green]✓[/green] Created client: {client_id}")
    console.print(f"\n[bold]Client Secret (save this!):[/bold]")
    console.print(f"[yellow]{client_secret}[/yellow]")


@clients.command("delete")
@click.argument("client_id")
async def clients_delete(client_id: str):
    """Delete a client credential."""
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    
    if await db.delete_client(client_id):
        console.print(f"[green]✓[/green] Deleted client: {client_id}")
    else:
        console.print(f"[red]Client not found: {client_id}[/red]")


@cli.command("run", context_settings=dict(ignore_unknown_options=True))
@click.option("--service", "-s", required=True, help="Service to inject key for")
@click.option("--project", "-p", type=int, help="Project ID for auditing")
@click.argument("command", nargs=-1, type=click.UNPROCESSED)
def run_command(service: str, project: int | None, command: list[str]):
    """Run a command with service API key injected into environment."""
    import os
    import subprocess
    import sys
    
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    api_key = vault.get_key(service)
    if not api_key:
        console.print(f"[red]Error:[/red] No key found for service '{service}'")
        sys.exit(1)
        
    env_name = SERVICE_ENV_MAP.get(service.lower(), f"{service.upper()}_API_KEY")
    
    new_env = os.environ.copy()
    new_env[env_name] = api_key
    
    # Audit logging (async)
    async def log():
        db = Database(settings.database_path)
        await db.init()
        await db.log_action(
            action="CLI_RUN",
            project_id=project,
            service=service,
            metadata={"command": " ".join(command)}
        )
    
    try:
        loop = asyncio.get_event_loop()
        if loop.is_running():
            loop.create_task(log())
        else:
            asyncio.run(log())
    except Exception:
        pass # Don't block if logging fails
        
    if not command:
        console.print(f"[yellow]No command specified. Injecting {env_name} into subshell...[/yellow]")
        command = [os.environ.get("SHELL", "bash")]

    console.print(f"[blue]Injecting {env_name} and running:[/blue] {' '.join(command)}")
    
    try:
        process = subprocess.Popen(
            command,
            env=new_env,
            shell=False
        )
        process.wait()
        sys.exit(process.returncode)
    except FileNotFoundError:
        console.print(f"[red]Error:[/red] Command not found: {command[0]}")
        sys.exit(1)
    except Exception as e:
        console.print(f"[red]Error:[/red] {e}")
        sys.exit(1)


def main():
    cli()


if __name__ == "__main__":
    main()
