import asyncio
import json
from typing import AsyncGenerator

import httpx

from keymaster_mcp.vault import Vault
from keymaster_mcp.config import get_settings
from keymaster_mcp.database import Database


class ProxyEngine:
    """Streaming proxy engine for LLM API requests."""

    OPENAI_BASE_URL = "https://api.openai.com/v1"

    def __init__(self):
        self.settings = get_settings()
        self.vault = Vault(self.settings.keymaster_vault_path)
        self.db = Database(self.settings.database_path)

    async def proxy_chat_completion(
        self,
        method: str,
        path: str,
        headers: dict,
        body: bytes,
        client_id: str | None = None,
        project_id: int | None = None,
        ip_address: str | None = None,
    ) -> AsyncGenerator[bytes, None]:
        """Proxy chat completion request with streaming support."""
        api_key = self.vault.get_key("openai")
        
        # Log the attempt
        await self.db.init()
        await self.db.log_action(
            action="PROXY_REQUEST",
            client_id=client_id,
            project_id=project_id,
            service="openai",
            ip_address=ip_address,
            status="pending"
        )

        if not api_key:
            await self.db.log_action(
                action="PROXY_ERROR",
                client_id=client_id,
                project_id=project_id,
                service="openai",
                status="error",
                metadata={"error": "OpenAI API key not configured"}
            )
            yield b'data: {"error": {"message": "OpenAI API key not configured", "type": "invalid_request_error"}}\n\n'
            return

        target_url = f"{self.OPENAI_BASE_URL}{path}"
        
        proxy_headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        }
        
        for header_name in ["Content-Type", "Accept"]:
            if header_name in headers:
                proxy_headers[header_name] = headers[header_name]

        async with httpx.AsyncClient(timeout=httpx.Timeout(300.0, connect=30.0)) as client:
            try:
                if method == "POST":
                    async with client.stream(
                        "POST",
                        target_url,
                        content=body,
                        headers=proxy_headers,
                    ) as response:
                        async for chunk in response.aiter_bytes():
                            if chunk:
                                yield chunk
                else:
                    async with client.get(target_url, headers=proxy_headers) as response:
                        yield await response.aread()
            except httpx.TimeoutException:
                yield b'data: {"error": {"message": "Request timeout", "type": "timeout_error"}}\n\n'
            except Exception as e:
                yield f'data: {{"error": {{"message": "{str(e)}", "type": "server_error"}}}}\n\n'.encode()

    async def proxy_completion(
        self,
        method: str,
        path: str,
        headers: dict,
        body: bytes,
        client_id: str | None = None,
        project_id: int | None = None,
        ip_address: str | None = None,
    ) -> AsyncGenerator[bytes, None]:
        """Proxy completion request with streaming support."""
        return await self.proxy_chat_completion(method, path, headers, body, client_id, project_id, ip_address)


proxy_engine = ProxyEngine()
