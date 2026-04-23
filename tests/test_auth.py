import pytest
import asyncio
import time
import hmac
import hashlib
import json

from keymaster_mcp.auth import HMACAuth
from keymaster_mcp.vault import Vault
from keymaster_mcp.database import Database


@pytest.fixture
def temp_vault(tmp_path):
    return Vault(str(tmp_path / "vault"))


@pytest.fixture
async def temp_db(tmp_path):
    db = Database(str(tmp_path / "test.db"))
    await db.init()
    return db


class TestVault:
    def test_set_and_get_key(self, temp_vault):
        temp_vault.set_key("openai", "sk-test-key")
        assert temp_vault.get_key("openai") == "sk-test-key"

    def test_get_nonexistent_key(self, temp_vault):
        assert temp_vault.get_key("openai") is None

    def test_delete_key(self, temp_vault):
        temp_vault.set_key("openai", "sk-test-key")
        assert temp_vault.delete_key("openai") is True
        assert temp_vault.get_key("openai") is None

    def test_list_services(self, temp_vault):
        temp_vault.set_key("openai", "sk-test-key")
        services = temp_vault.list_services()
        assert "openai" in services
        assert "anthropic" not in services

    def test_has_key(self, temp_vault):
        assert temp_vault.has_key("openai") is False
        temp_vault.set_key("openai", "sk-test-key")
        assert temp_vault.has_key("openai") is True

    def test_rotate_key(self, temp_vault):
        temp_vault.set_key("openai", "sk-old-key")
        temp_vault.rotate_key("openai", "sk-new-key")
        assert temp_vault.get_key("openai") == "sk-new-key"

    def test_unsupported_service(self, temp_vault):
        with pytest.raises(ValueError):
            temp_vault.set_key("unsupported", "some-key")


class TestDatabase:
    @pytest.mark.asyncio
    async def test_create_and_get_client(self, temp_db):
        client_id, secret = await temp_db.create_client("test-client", "Test Client")
        assert client_id == "test-client"
        
        client = await temp_db.get_client("test-client")
        assert client is not None
        assert client["client_id"] == "test-client"

    @pytest.mark.asyncio
    async def test_verify_client_secret(self, temp_db):
        client_id, secret = await temp_db.create_client("test-client")
        
        assert await temp_db.verify_client_secret("test-client", secret) is True
        assert await temp_db.verify_client_secret("test-client", "wrong-secret") is False

    @pytest.mark.asyncio
    async def test_list_clients(self, temp_db):
        await temp_db.create_client("client1")
        await temp_db.create_client("client2")
        
        clients = await temp_db.list_clients()
        assert len(clients) == 2

    @pytest.mark.asyncio
    async def test_delete_client(self, temp_db):
        await temp_db.create_client("test-client")
        assert await temp_db.delete_client("test-client") is True
        assert await temp_db.get_client("test-client") is None


class TestHMACAuth:
    @pytest.mark.asyncio
    async def test_verify_timestamp_valid(self, temp_db):
        auth = HMACAuth()
        auth.db = temp_db
        
        await temp_db.create_client("test-client", "Test")
        
        timestamp = str(int(time.time()))
        assert await auth.verify_timestamp(timestamp) is True

    @pytest.mark.asyncio
    async def test_verify_timestamp_expired(self, temp_db):
        auth = HMACAuth()
        auth.db = temp_db
        
        await temp_db.create_client("test-client", "Test")
        
        old_timestamp = str(int(time.time()) - 60)
        assert await auth.verify_timestamp(old_timestamp) is False

    @pytest.mark.asyncio
    async def test_verify_timestamp_invalid(self, temp_db):
        auth = HMACAuth()
        
        assert await auth.verify_timestamp("invalid") is False

    @pytest.mark.asyncio
    async def test_verify_signature(self, temp_db):
        auth = HMACAuth()
        auth.db = temp_db
        
        client_id, secret = await temp_db.create_client("test-client")
        
        timestamp = str(int(time.time()))
        body = b'{"test": "data"}'
        message = f"POST:/api/test:{timestamp}:".encode() + body
        client = await temp_db.get_client(client_id)
        secret_hash = client["client_secret_hash"]
        expected_sig = hmac.new(
            secret_hash.encode(),
            message,
            hashlib.sha256
        ).hexdigest()
        
        assert await auth.verify_signature(
            "POST", "/api/test", timestamp, body, client_id, expected_sig
        ) is True

        assert await auth.verify_signature(
            "POST", "/api/test", timestamp, body, client_id, "invalid-signature"
        ) is False
