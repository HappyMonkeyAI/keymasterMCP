import hmac
import hashlib
import secrets
import time
from typing import Optional
from fastapi import Request, HTTPException, Depends
from fastapi.security import APIKeyHeader
from starlette.datastructures import Headers

from keymaster_mcp.config import get_settings
from keymaster_mcp.database import Database


async def get_db() -> Database:
    """Dependency to get database instance."""
    db = Database()
    await db.init()
    return db


class HMACAuth:
    """HMAC-based authentication middleware."""

    def __init__(self):
        self.settings = get_settings()
        self.db = Database()

    async def verify_signature(
        self,
        method: str,
        path: str,
        timestamp: str,
        body: bytes,
        client_id: str,
        signature: str,
    ) -> bool:
        """Verify HMAC signature."""
        client = await self.db.get_client(client_id)
        if not client:
            return False

        secret = client["client_secret_hash"]
        message = f"{method}:{path}:{timestamp}:".encode() + body
        expected_signature = hmac.new(
            secret.encode(),
            message,
            hashlib.sha256
        ).hexdigest()

        return hmac.compare_digest(signature, expected_signature)

    async def verify_timestamp(self, timestamp: str) -> bool:
        """Verify timestamp is within tolerance."""
        try:
            ts = int(timestamp)
            current_ts = int(time.time())
            tolerance = self.settings.timestamp_tolerance_seconds
            return abs(current_ts - ts) <= tolerance
        except (ValueError, TypeError):
            return False

    async def __call__(self, request: Request) -> str:
        """Middleware call to verify request."""
        timestamp = request.headers.get("x-timestamp")
        signature = request.headers.get("x-signature")
        client_id = request.headers.get("x-client-id")

        if not all([timestamp, signature, client_id]):
            raise HTTPException(
                status_code=401,
                detail="Missing required headers: x-timestamp, x-signature, x-client-id"
            )

        if not await self.verify_timestamp(timestamp):
            raise HTTPException(
                status_code=401,
                detail="Request timestamp expired"
            )

        body = await request.body()
        
        if not await self.verify_signature(
            request.method,
            request.url.path,
            timestamp,
            body,
            client_id,
            signature
        ):
            raise HTTPException(
                status_code=401,
                detail="Invalid signature"
            )

        await self.db.update_last_used(client_id)
        return client_id


class ProjectAPIKeyAuth:
    """Project-based API key authentication with IP whitelist."""

    def __init__(self):
        self.settings = get_settings()
        self.db = Database()

    async def verify_project_api_key(
        self,
        api_key: str,
        project_id: int,
        client_ip: str,
    ) -> Optional[dict]:
        """Verify project API key and IP whitelist."""
        project = await self.db.get_project(project_id)
        if not project:
            return None

        if not await self.db.is_ip_allowed(project_id, client_ip):
            raise HTTPException(
                status_code=403,
                detail="IP address not whitelisted for this project"
            )

        return project

    async def __call__(self, request: Request, project_id: int) -> dict:
        """Middleware call to verify project API key."""
        api_key = request.headers.get("x-api-key")
        
        if not api_key:
            raise HTTPException(
                status_code=401,
                detail="Missing required header: x-api-key"
            )

        client_ip = request.client.host if request.client else "unknown"

        project = await self.verify_project_api_key(api_key, project_id, client_ip)
        if not project:
            raise HTTPException(
                status_code=401,
                detail="Invalid API key"
            )

        return project


async def require_hmac_auth(request: Request) -> str:
    """FastAPI dependency for HMAC authentication."""
    auth = HMACAuth()
    return await auth(request)


async def require_project_auth(request: Request, project_id: int) -> dict:
    """FastAPI dependency for project API key authentication."""
    auth = ProjectAPIKeyAuth()
    return await auth(request, project_id)


hmac_auth = HMACAuth()
project_auth = ProjectAPIKeyAuth()


async def hmac_middleware(request: Request, call_next):
    """Middleware for HMAC authentication (skips /health and /mcp)."""
    if request.url.path in ["/health", "/mcp"] or request.url.path.startswith("/docs"):
        return await call_next(request)
    
    try:
        await hmac_auth(request)
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=401, detail=str(e))
    
    return await call_next(request)
