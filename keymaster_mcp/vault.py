import os
import json
import hashlib
from pathlib import Path
from typing import Optional
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC


class Vault:
    """Encrypted vault for storing API credentials using Fernet encryption."""

    SUPPORTED_SERVICES = ["openai", "anthropic", "github"]

    def __init__(self, vault_path: str = "./vault"):
        self.vault_path = Path(vault_path)
        self.keys_file = self.vault_path / "keys.enc"
        self.vault_path.mkdir(parents=True, exist_ok=True)
        self._fernet = self._get_fernet()

    def _get_fernet(self) -> Fernet:
        """Get or create Fernet encryption instance."""
        key_file = self.vault_path / ".key"
        
        if key_file.exists():
            key = key_file.read_bytes()
        else:
            key = Fernet.generate_key()
            key_file.write_bytes(key)
            key_file.chmod(0o600)
        
        return Fernet(key)

    def _load_keys(self) -> dict:
        """Load encrypted keys from disk."""
        if not self.keys_file.exists():
            return {}
        try:
            encrypted = self.keys_file.read_bytes()
            if not encrypted:
                return {}
            decrypted = self._fernet.decrypt(encrypted)
            return json.loads(decrypted)
        except Exception:
            return {}

    def _save_keys(self, keys: dict) -> None:
        """Save encrypted keys to disk."""
        encrypted = self._fernet.encrypt(json.dumps(keys).encode())
        self.keys_file.write_bytes(encrypted)
        self.keys_file.chmod(0o600)

    def set_key(self, service: str, api_key: str, environment: Optional[str] = None) -> None:
        """Store an API key for a service and optional environment."""
        if service not in self.SUPPORTED_SERVICES:
            raise ValueError(f"Unsupported service: {service}. Supported: {self.SUPPORTED_SERVICES}")
        
        keys = self._load_keys()
        storage_key = f"{service}:{environment}" if environment else service
        keys[storage_key] = api_key
        self._save_keys(keys)

    def get_key(self, service: str, environment: Optional[str] = None) -> Optional[str]:
        """Retrieve an API key for a service and optional environment."""
        keys = self._load_keys()
        
        # Try environment specific key first
        if environment:
            storage_key = f"{service}:{environment}"
            if storage_key in keys:
                return keys[storage_key]
        
        # Fallback to default service key
        return keys.get(service)

    def delete_key(self, service: str) -> bool:
        """Delete an API key for a service."""
        keys = self._load_keys()
        if service in keys:
            del keys[service]
            self._save_keys(keys)
            return True
        return False

    def list_services(self) -> list[str]:
        """List all services with stored keys."""
        keys = self._load_keys()
        return [s for s in self.SUPPORTED_SERVICES if s in keys]

    def has_key(self, service: str) -> bool:
        """Check if a service has a key configured."""
        return self.get_key(service) is not None

    def rotate_key(self, service: str, new_api_key: str) -> None:
        """Rotate an API key for a service (delete and set new)."""
        self.delete_key(service)
        self.set_key(service, new_api_key)
