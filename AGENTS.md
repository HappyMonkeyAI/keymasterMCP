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
│   ├── database.py         # SQLite (clients, projects, organization)
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
│   │       ├── CredentialsController.php
│   │       ├── SettingsController.php
│   │       └── AccessControlController.php
│   └── public/index.php    # Router
└── docker-compose.yml
```

## Adding New Services

### 1. Store in Vault
The vault now supports arbitrary service identifiers. Use `Vault.set_key(service, api_key)`.

### 2. Register Metadata
Register the service in the `credential_registry` table to enable it in the dashboard.

## Database Schema

```sql
-- Organization (Singleton metadata)
CREATE TABLE organization (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clients (API consumers with RBAC)
CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id TEXT UNIQUE NOT NULL,
    client_secret_hash TEXT NOT NULL,
    name TEXT,
    email TEXT,
    role TEXT DEFAULT 'developer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP
);

-- Projects (isolated credential groups with types)
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    description TEXT,
    type TEXT DEFAULT 'secrets',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Project credential assignments
CREATE TABLE project_credentials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    service TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE(project_id, service)
);

-- Credential Registry (Metadata for services)
CREATE TABLE credential_registry (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service TEXT NOT NULL UNIQUE,
    display_name TEXT,
    group_id INTEGER,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES credential_groups(id) ON DELETE SET NULL
);
```

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/health` | None | Health check |
| GET | `/api/organization` | HMAC | Get org details |
| PUT | `/api/organization` | HMAC | Update org details |
| GET | `/api/services` | HMAC | List vault services |
| POST | `/api/keys` | HMAC | Add API key |
| GET | `/api/projects` | HMAC | List projects |
| POST | `/api/projects` | HMAC | Create project |
| GET | `/api/projects/{id}` | HMAC | Project details |
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
