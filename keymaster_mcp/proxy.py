import asyncio
import json
from typing import AsyncGenerator

import httpx

from keymaster_mcp.vault import Vault
from keymaster_mcp.config import get_settings
from keymaster_mcp.database import Database


class ProxyEngine:
    """Streaming proxy engine for LLM API requests."""

    DEFAULT_BASE_URLS = {
        "openai": "https://api.openai.com/v1",
        "anthropic": "https://api.anthropic.com/v1",
        "github": "https://api.github.com",
    }

    def __init__(self):
        self.settings = get_settings()
        self.vault = Vault(self.settings.keymaster_vault_path)
        self.db = Database(self.settings.database_path)

    async def _resolve_service_config(self, service: str) -> tuple[str, str]:
        """Resolve the base URL and API key for a given service."""
        # 1. Try to get custom URL from vault
        base_url = self.vault.get_key(f"{service.upper()}_URL") or \
                   self.vault.get_key(f"{service}_url") or \
                   self.DEFAULT_BASE_URLS.get(service.lower())

        # 2. Try to get API key from vault
        api_key = self.vault.get_key(service.lower()) or \
                  self.vault.get_key(f"{service.upper()}_API_KEY")

        return base_url, api_key

    async def proxy_chat_completion(
        self,
        method: str,
        path: str,
        headers: dict,
        body: bytes,
        service: str = "openai",
        client_id: str | None = None,
        project_id: int | None = None,
        ip_address: str | None = None,
    ) -> AsyncGenerator[bytes, None]:
        """Proxy chat completion request with streaming support."""
        base_url, api_key = await self._resolve_service_config(service)
        
        # Log the attempt
        await self.db.init()
        await self.db.log_action(
            action="PROXY_REQUEST",
            client_id=client_id,
            project_id=project_id,
            service=service,
            ip_address=ip_address,
            status="pending",
            metadata={"target": base_url}
        )

        if not base_url:
            yield b'data: {"error": {"message": "Base URL not configured for service", "type": "invalid_request_error"}}\n\n'
            return

        if not api_key:
            await self.db.log_action(
                action="PROXY_ERROR",
                client_id=client_id,
                project_id=project_id,
                service=service,
                status="error",
                metadata={"error": f"{service} API key not configured"}
            )
            yield f'data: {{"error": {{"message": "{service} API key not configured", "type": "invalid_request_error"}}}}\n\n'.encode()
            return

        target_url = f"{base_url.rstrip('/')}{path}"
        
        proxy_headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        }
        
        # Forward relevant headers
        for header_name in ["Content-Type", "Accept", "Anthropic-Version"]:
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
        service: str = "openai",
        client_id: str | None = None,
        project_id: int | None = None,
        ip_address: str | None = None,
    ) -> AsyncGenerator[bytes, None]:
        """Proxy completion request with streaming support."""
        return await self.proxy_chat_completion(method, path, headers, body, service, client_id, project_id, ip_address)


proxy_engine = ProxyEngine()
