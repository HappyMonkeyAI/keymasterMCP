import asyncio
import os
import json
from pathlib import Path
from keymaster_mcp.database import Database
from keymaster_mcp.vault import Vault

# Path to the .env file (passed from host)
ENV_FILE = "/app/test-env.env"
DB_PATH = "/data/keymaster.db"
VAULT_PATH = "/app/vault"

async def populate():
    db = Database(DB_PATH)
    await db.init()
    vault = Vault(VAULT_PATH)
    
    # 1. Parse .env
    if not os.path.exists(ENV_FILE):
        print(f"Error: {ENV_FILE} not found")
        return

    with open(ENV_FILE, "r") as f:
        lines = f.readlines()

    credentials = {}
    current_comment = None
    for line in lines:
        line = line.strip()
        if not line:
            continue
        if line.startswith("#"):
            current_comment = line.lstrip("# ").strip()
            continue
        
        if "=" in line:
            key, value = line.split("=", 1)
            key = key.strip()
            value = value.strip().strip("\"'")
            if key:
                credentials[key] = {
                    "value": value,
                    "description": current_comment
                }
            current_comment = None

    # 2. Get or create "Aura.AI" group
    groups = await db.list_credential_groups()
    aura_group = next((g for g in groups if g["name"] == "Aura.AI"), None)
    if not aura_group:
        group_id = await db.create_credential_group("Aura.AI", "Credentials and configuration for Aura.AI chatbot")
    else:
        group_id = aura_group["id"]

    # 3. Get Aura.AI project ID
    projects = await db.list_projects()
    project = next((p for p in projects if p["name"] == "Aura.AI"), None)
    if not project:
        print("Error: Aura.AI project not found in database")
        return
    project_id = project["id"]

    # 4. Register and Store each credential
    for service, data in credentials.items():
        # Store in vault
        vault.set_key(service, data["value"])
        
        # Register in DB
        await db.register_credential(
            service=service,
            display_name=service.replace("_", " ").title(),
            group_id=group_id,
            description=data["description"]
        )
        
        # Assign to project
        await db.add_project_credential(project_id, service)
        print(f"Populated: {service}")

    print("Success: Aura.AI project populated.")

if __name__ == "__main__":
    asyncio.run(populate())
