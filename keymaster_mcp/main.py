import asyncio
import json
from contextlib import asynccontextmanager
from typing import AsyncGenerator

from fastapi import FastAPI, Request, Response, Depends
from fastapi.middleware.cors import CORSMiddleware
from sse_starlette.sse import EventSourceResponse

from keymaster_mcp.config import get_settings
from keymaster_mcp.vault import Vault
from keymaster_mcp.database import Database
from keymaster_mcp.auth import require_hmac_auth, require_project_auth
from keymaster_mcp.proxy import proxy_engine
from keymaster_mcp.models import (
    ServiceInfo, ClientInfo, CreateClientRequest,
    CreateClientResponse, AddKeyRequest, RotateKeyRequest,
    ProjectRequest, ProjectResponse, ProjectDetailResponse,
    AddCredentialRequest, AddIPRequest,
)


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    yield


app = FastAPI(
    title="Keymaster MCP",
    description="Secure API Credentials Vault and Proxy Server for AI tools using MCP",
    version="0.1.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health")
async def health_check():
    return {"status": "healthy"}


@app.get("/api/services", response_model=list[ServiceInfo])
async def list_services():
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    return [
        {"name": service, "configured": vault.has_key(service)}
        for service in Vault.SUPPORTED_SERVICES
    ]


@app.post("/api/keys", status_code=201)
async def add_key(request: AddKeyRequest, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    vault.set_key(request.service, request.api_key)
    return {"message": f"Key for {request.service} added successfully"}


@app.post("/api/keys/rotate")
async def rotate_key(request: RotateKeyRequest, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    vault.rotate_key(request.service, request.new_api_key)
    return {"message": f"Key for {request.service} rotated successfully"}


@app.delete("/api/keys/{service}")
async def delete_key(service: str, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    deleted = vault.delete_key(service)
    if deleted:
        return {"message": f"Key for {service} deleted successfully"}
    return {"error": f"No key found for {service}"}, 404


@app.get("/api/clients", response_model=list[ClientInfo])
async def list_clients(_: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    clients = await db.list_clients()
    return [
        {
            "client_id": c["client_id"],
            "name": c.get("name"),
            "created_at": c.get("created_at", ""),
            "last_used_at": c.get("last_used_at"),
        }
        for c in clients
    ]


@app.post("/api/clients", response_model=CreateClientResponse)
async def create_client(request: CreateClientRequest):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    client_id, client_secret = await db.create_client(
        request.client_id,
        request.name,
    )
    return {"client_id": client_id, "client_secret": client_secret}


@app.delete("/api/clients/{client_id}")
async def delete_client(client_id: str, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    deleted = await db.delete_client(client_id)
    if deleted:
        return {"message": f"Client {client_id} deleted successfully"}
    return {"error": f"Client {client_id} not found"}, 404


@app.get("/api/projects", response_model=list[ProjectResponse])
async def list_projects(_: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    projects = await db.list_projects()
    return [
        {
            "id": p["id"],
            "name": p["name"],
            "description": p.get("description"),
            "created_at": p.get("created_at", ""),
            "updated_at": p.get("updated_at", ""),
        }
        for p in projects
    ]


@app.get("/api/audit-logs")
async def list_audit_logs(limit: int = 100, project_id: int | None = None, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    logs = await db.get_audit_logs(limit=limit, project_id=project_id)
    return logs


@app.post("/api/projects", response_model=ProjectResponse)
async def create_project(request: ProjectRequest, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    project = await db.create_project(request.name, request.description)
    return project


@app.get("/api/projects/{project_id}", response_model=ProjectDetailResponse)
async def get_project(project_id: int, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    project = await db.get_project(project_id)
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    credentials = await db.get_project_credentials(project_id)
    ips = await db.get_project_ips(project_id)
    
    return {
        "id": project["id"],
        "name": project["name"],
        "description": project.get("description"),
        "created_at": project.get("created_at", ""),
        "updated_at": project.get("updated_at", ""),
        "credentials": [c["service"] for c in credentials],
        "ips": ips,
    }


@app.put("/api/projects/{project_id}", response_model=ProjectResponse)
async def update_project(project_id: int, request: ProjectRequest, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    project = await db.update_project(project_id, request.name, request.description)
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    return project


@app.delete("/api/projects/{project_id}")
async def delete_project(project_id: int, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    deleted = await db.delete_project(project_id)
    if deleted:
        return {"message": f"Project {project_id} deleted successfully"}
    return {"error": f"Project {project_id} not found"}, 404


@app.post("/api/projects/{project_id}/credentials")
async def add_project_credential(project_id: int, request: AddCredentialRequest, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    vault = Vault(settings.keymaster_vault_path)
    
    if not vault.has_key(request.service):
        raise HTTPException(status_code=400, detail=f"Service '{request.service}' not configured in vault")
    
    db = Database(settings.database_path)
    await db.init()
    
    project = await db.get_project(project_id)
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    await db.add_project_credential(project_id, request.service)
    return {"message": f"Credential '{request.service}' added to project"}


@app.delete("/api/projects/{project_id}/credentials/{service}")
async def remove_project_credential(project_id: int, service: str, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    deleted = await db.remove_project_credential(project_id, service)
    if deleted:
        return {"message": f"Credential '{service}' removed from project"}
    return {"error": "Credential not found"}, 404


@app.post("/api/projects/{project_id}/ips")
async def add_project_ip(project_id: int, request: AddIPRequest, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    
    project = await db.get_project(project_id)
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    try:
        result = await db.add_project_ip(project_id, request.ip_address)
        return {"message": f"IP '{request.ip_address}' added to whitelist"}
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@app.delete("/api/projects/{project_id}/ips/{ip_address}")
async def remove_project_ip(project_id: int, ip_address: str, _: str = Depends(require_hmac_auth)):
    settings = get_settings()
    db = Database(settings.database_path)
    await db.init()
    deleted = await db.remove_project_ip(project_id, ip_address)
    if deleted:
        return {"message": f"IP '{ip_address}' removed from whitelist"}
    return {"error": "IP not found"}, 404


@app.api_route("/v1/chat/completions", methods=["GET", "POST"])
async def proxy_chat_completions(request: Request):
    """Proxy OpenAI chat completions with streaming support."""
    client_id = await require_hmac_auth(request)
    
    body = await request.body()
    headers = dict(request.headers)
    client_ip = request.client.host if request.client else "unknown"
    
    async def generate() -> AsyncGenerator[dict, None]:
        async for chunk in proxy_engine.proxy_chat_completion(
            request.method,
            "/chat/completions",
            headers,
            body,
            client_id=client_id,
            ip_address=client_ip
        ):
            yield {"data": chunk.decode("utf-8", errors="replace")}

    return EventSourceResponse(generate())


@app.api_route("/v1/completions", methods=["GET", "POST"])
async def proxy_completions(request: Request):
    """Proxy OpenAI completions with streaming support."""
    client_id = await require_hmac_auth(request)
    
    body = await request.body()
    headers = dict(request.headers)
    client_ip = request.client.host if request.client else "unknown"
    
    async def generate() -> AsyncGenerator[dict, None]:
        async for chunk in proxy_engine.proxy_completion(
            request.method,
            "/completions",
            headers,
            body,
            client_id=client_id,
            ip_address=client_ip
        ):
            yield {"data": chunk.decode("utf-8", errors="replace")}

    return EventSourceResponse(generate())


def main():
    """Entry point for running the server."""
    import uvicorn
    uvicorn.run(
        "keymaster_mcp.app:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
    )


if __name__ == "__main__":
    main()
