Developer documentation for working with Keymaster MCP.

## 🧠 Core Identity: The Keymaster Engineer
Agents operating on this repository act as autonomous Security and Staff Engineers. The primary directive is to deliver robust, secure, and traceable credential management solutions with zero friction.

## 🛠 Trinity Orchestration (Self-Evolution)
We utilize three specialized analytical lenses to optimize project velocity:
- **[Echo] Structural Memory**: Detects patterns and extracts lessons to `.antigravity/memories/patterns_and_lessons.md`.
- **[Ripple] Relational Patterns**: Analyzes the "blast radius" of changes across dependencies (Vault -> DB -> API -> Proxy -> Frontend).
- **[Pulse] Velocity Monitor**: Halts failing paths, resets state, and pivots to lower-gravity approaches if momentum stalls.

## 🚦 Autonomous Protocols
- **The Ratchet**: After a successful test run or manual verification, the agent should automatically perform `git add` and `git commit` without prompting.
- **Pulse Reset**: After three consecutive verification failures, the agent should execute `git reset --hard HEAD` to revert to the last clean state and reconsider the approach.

## 📝 Long-Term Memory (LTM)
Persistent project knowledge is stored in `.antigravity/memories/`:
- `codebase_insights/`: High-level summaries of complex modules.
- `architectural_decisions/`: Logs of major design choices (ADRs).
- `patterns_and_lessons.md`: Success logs, post-mortems, and recurring patterns.

## Project Structure

```
keymasterMCP/
├── keymaster_mcp/          # Python backend
│   ├── main.py             # FastAPI entry point
│   ├── config.py           # Settings (pydantic)
│   ├── vault.py            # Encrypted Fernet vault
│   ├── database.py         # SQLite (clients, projects)
│   ├── auth.py             # HMAC authentication
│   ├── proxy.py            # SSE streaming proxy
│   ├── mcp_server.py       # MCP tools
│   ├── models.py           # Pydantic schemas
│   └── cli.py              # CLI commands
├── php/                    # PHP frontend
│   ├── src/
│   │   ├── Services/ApiService.php
│   │   └── Controllers/
│   │       ├── AuthController.php
│   │       ├── DashboardController.php
│   │       ├── ProjectsController.php
│   │       └── CredentialsController.php
│   └── public/index.php    # Router
└── docker-compose.yml
```

## Adding New Services

### 1. Add to Supported Services

Edit `keymaster_mcp/vault.py`:

```python
SUPPORTED_SERVICES = ["openai", "anthropic", "github", "new-service"]
```

### 2. Add Proxy Endpoint

Edit `keymaster_mcp/proxy.py`:

```python
async def proxy_new_service(self, method, path, headers, body):
    # Forward to external API
    target_url = f"https://api.newservice.com/v1{path}"
    # ... implementation
```

## Database Schema

```sql
-- Clients (API consumers)
CREATE TABLE clients (
    client_id TEXT PRIMARY KEY,
    client_secret_hash TEXT NOT NULL,
    name TEXT,
    created_at TIMESTAMP,
    last_used_at TIMESTAMP
);

-- Projects (isolated credential groups)
CREATE TABLE projects (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Project credential assignments
CREATE TABLE project_credentials (
    project_id INTEGER,
    service TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- IP whitelist per project
CREATE TABLE project_ip_whitelist (
    project_id INTEGER,
    ip_address TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- Audit logs (Secret access tracking)
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    client_id TEXT,
    project_id INTEGER,
    service TEXT,
    action TEXT NOT NULL,
    ip_address TEXT,
    status TEXT,
    metadata TEXT
);
```

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/health` | None | Health check |
| GET | `/api/services` | HMAC | List vault services |
| POST | `/api/keys` | HMAC | Add API key |
| POST | `/api/keys/rotate` | HMAC | Rotate API key |
| DELETE | `/api/keys/{service}` | HMAC | Delete API key |
| GET | `/api/projects` | HMAC | List projects |
| POST | `/api/projects` | HMAC | Create project |
| GET | `/api/projects/{id}` | HMAC | Project details |
| POST | `/api/projects/{id}/credentials` | HMAC | Add credential |
| POST | `/api/projects/{id}/ips` | HMAC | Add IP whitelist |
| POST | `/api/clients` | None | Create client (bootstrap) |
| GET/POST | `/v1/chat/completions` | HMAC | Proxy to OpenAI |

## Testing

```bash
# Run Python tests
pytest tests/

# Test API manually
curl http://localhost:9000/health

# Test auth
timestamp=$(date +%s)
signature=$(echo -n "GET:/api/services:$timestamp:" | openssl dgst -sha256 -hmac "secret")
curl -H "X-Client-Id: test" -H "X-Timestamp: $timestamp" -H "X-Signature: $signature" \
  http://localhost:9000/api/services
```

## Security Considerations

- Vault key (`vault/.key`) has 0o600 permissions
- HMAC uses timing-safe comparison (`hmac.compare_digest`)
- Timestamps validated within 30-second window
- IP whitelist checked per-project before proxy
- Client secrets hashed with SHA256 (consider argon2 for production)
