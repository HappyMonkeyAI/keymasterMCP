import aiosqlite
import hashlib
import secrets
import ipaddress
from datetime import datetime
from pathlib import Path
from typing import Optional

from keymaster_mcp.config import get_settings


class Database:
    """SQLite database for tracking client credentials and metadata."""

    def __init__(self, db_path: Optional[str] = None):
        self.db_path = Path(db_path or get_settings().database_path)
        self.db_path.parent.mkdir(parents=True, exist_ok=True)

    async def init(self) -> None:
        """Initialize database schema."""
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute("""
                CREATE TABLE IF NOT EXISTS clients (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    client_id TEXT UNIQUE NOT NULL,
                    client_secret_hash TEXT NOT NULL,
                    name TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_used_at TIMESTAMP
                )
            """)
            
            await db.execute("""
                CREATE TABLE IF NOT EXISTS projects (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            
            await db.execute("""
                CREATE TABLE IF NOT EXISTS project_credentials (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_id INTEGER NOT NULL,
                    service TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                    UNIQUE(project_id, service)
                )
            """)
            
            await db.execute("""
                CREATE TABLE IF NOT EXISTS project_api_keys (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_id INTEGER NOT NULL,
                    api_key TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
                )
            """)
            
            await db.execute("""
                CREATE TABLE IF NOT EXISTS project_ip_whitelist (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_id INTEGER NOT NULL,
                    ip_address TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
                )
            """)

            await db.execute("""
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    client_id TEXT,
                    project_id INTEGER,
                    service TEXT,
                    action TEXT NOT NULL,
                    ip_address TEXT,
                    status TEXT,
                    metadata TEXT
                )
            """)
            
            await db.commit()

    async def create_client(self, client_id: str, name: Optional[str] = None) -> tuple[str, str]:
        """Create a new client. Returns (client_id, client_secret)."""
        client_secret = secrets.token_urlsafe(32)
        secret_hash = hashlib.sha256(client_secret.encode()).hexdigest()
        
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                "INSERT INTO clients (client_id, client_secret_hash, name) VALUES (?, ?, ?)",
                (client_id, secret_hash, name or client_id)
            )
            await db.commit()
        
        return client_id, client_secret

    async def get_client(self, client_id: str) -> Optional[dict]:
        """Get client by ID."""
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            async with db.execute(
                "SELECT * FROM clients WHERE client_id = ?",
                (client_id,)
            ) as cursor:
                row = await cursor.fetchone()
                if row:
                    return dict(row)
        return None

    async def verify_client_secret(self, client_id: str, client_secret: str) -> bool:
        """Verify client secret."""
        client = await self.get_client(client_id)
        if not client:
            return False
        secret_hash = hashlib.sha256(client_secret.encode()).hexdigest()
        return secret_hash == client["client_secret_hash"]

    async def update_last_used(self, client_id: str) -> None:
        """Update last used timestamp."""
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                "UPDATE clients SET last_used_at = ? WHERE client_id = ?",
                (datetime.utcnow().isoformat(), client_id)
            )
            await db.commit()

    async def list_clients(self) -> list[dict]:
        """List all clients."""
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            async with db.execute(
                "SELECT client_id, name, created_at, last_used_at FROM clients"
            ) as cursor:
                rows = await cursor.fetchall()
                return [dict(row) for row in rows]

    async def delete_client(self, client_id: str) -> bool:
        """Delete a client."""
        async with aiosqlite.connect(self.db_path) as db:
            cursor = await db.execute(
                "DELETE FROM clients WHERE client_id = ?",
                (client_id,)
            )
            await db.commit()
            return cursor.rowcount > 0

    async def create_project(self, name: str, description: Optional[str] = None) -> dict:
        """Create a new project."""
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                "INSERT INTO projects (name, description) VALUES (?, ?)",
                (name, description)
            )
            await db.commit()
            project_id = db.lastrowid
        
        return await self.get_project(project_id)

    async def get_project(self, project_id: int) -> Optional[dict]:
        """Get project by ID."""
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            async with db.execute(
                "SELECT * FROM projects WHERE id = ?",
                (project_id,)
            ) as cursor:
                row = await cursor.fetchone()
                if row:
                    return dict(row)
        return None

    async def list_projects(self) -> list[dict]:
        """List all projects."""
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            async with db.execute("SELECT * FROM projects ORDER BY created_at DESC") as cursor:
                rows = await cursor.fetchall()
                return [dict(row) for row in rows]

    async def update_project(self, project_id: int, name: str, description: Optional[str] = None) -> Optional[dict]:
        """Update a project."""
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                "UPDATE projects SET name = ?, description = ?, updated_at = ? WHERE id = ?",
                (name, description, datetime.utcnow().isoformat(), project_id)
            )
            await db.commit()
        return await self.get_project(project_id)

    async def delete_project(self, project_id: int) -> bool:
        """Delete a project."""
        async with aiosqlite.connect(self.db_path) as db:
            cursor = await db.execute("DELETE FROM projects WHERE id = ?", (project_id,))
            await db.commit()
            return cursor.rowcount > 0

    async def add_project_credential(self, project_id: int, service: str) -> dict:
        """Add a service credential to a project."""
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                "INSERT OR REPLACE INTO project_credentials (project_id, service) VALUES (?, ?)",
                (project_id, service)
            )
            await db.commit()
        
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            async with db.execute(
                "SELECT * FROM project_credentials WHERE project_id = ? AND service = ?",
                (project_id, service)
            ) as cursor:
                return dict(await cursor.fetchone())

    async def remove_project_credential(self, project_id: int, service: str) -> bool:
        """Remove a service credential from a project."""
        async with aiosqlite.connect(self.db_path) as db:
            cursor = await db.execute(
                "DELETE FROM project_credentials WHERE project_id = ? AND service = ?",
                (project_id, service)
            )
            await db.commit()
            return cursor.rowcount > 0

    async def get_project_credentials(self, project_id: int) -> list[dict]:
        """Get all credentials for a project."""
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            async with db.execute(
                "SELECT service FROM project_credentials WHERE project_id = ?",
                (project_id,)
            ) as cursor:
                return [dict(row) for row in await cursor.fetchall()]

    async def add_project_ip(self, project_id: int, ip_address: str) -> dict:
        """Add an IP to project's whitelist."""
        ipaddress.ip_address(ip_address)
        
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                "INSERT INTO project_ip_whitelist (project_id, ip_address) VALUES (?, ?)",
                (project_id, ip_address)
            )
            await db.commit()
            ip_id = db.lastrowid
        
        return {"id": ip_id, "project_id": project_id, "ip_address": ip_address}

    async def remove_project_ip(self, project_id: int, ip_address: str) -> bool:
        """Remove an IP from project's whitelist."""
        async with aiosqlite.connect(self.db_path) as db:
            cursor = await db.execute(
                "DELETE FROM project_ip_whitelist WHERE project_id = ? AND ip_address = ?",
                (project_id, ip_address)
            )
            await db.commit()
            return cursor.rowcount > 0

    async def get_project_ips(self, project_id: int) -> list[str]:
        """Get all whitelisted IPs for a project."""
        async with aiosqlite.connect(self.db_path) as db:
            async with db.execute(
                "SELECT ip_address FROM project_ip_whitelist WHERE project_id = ?",
                (project_id,)
            ) as cursor:
                rows = await cursor.fetchall()
                return [row[0] for row in rows]

    async def is_ip_allowed(self, project_id: int, ip_address: str) -> bool:
        """Check if an IP is whitelisted for a project."""
        ips = await self.get_project_ips(project_id)
        if not ips:
            return True
        return ip_address in ips

    async def log_action(
        self,
        action: str,
        client_id: Optional[str] = None,
        project_id: Optional[int] = None,
        service: Optional[str] = None,
        ip_address: Optional[str] = None,
        status: str = "success",
        metadata: Optional[dict] = None
    ) -> None:
        """Record an action in the audit log."""
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                """
                INSERT INTO audit_logs 
                (client_id, project_id, service, action, ip_address, status, metadata) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    client_id, 
                    project_id, 
                    service, 
                    action, 
                    ip_address, 
                    status, 
                    json.dumps(metadata) if metadata else None
                )
            )
            await db.commit()

    async def get_audit_logs(self, limit: int = 100, project_id: Optional[int] = None) -> list[dict]:
        """Retrieve audit logs."""
        async with aiosqlite.connect(self.db_path) as db:
            db.row_factory = aiosqlite.Row
            query = "SELECT * FROM audit_logs"
            params = []
            if project_id:
                query += " WHERE project_id = ?"
                params.append(project_id)
            query += " ORDER BY timestamp DESC LIMIT ?"
            params.append(limit)
            
            async with db.execute(query, params) as cursor:
                rows = await cursor.fetchall()
                return [dict(row) for row in rows]
