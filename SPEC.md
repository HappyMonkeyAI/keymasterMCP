# Keymaster MCP - Implementation Specification

## 1. Project Overview

**Project Name:** Keymaster MCP  
**Type:** API Credentials Vault and Proxy Server  
**Core Functionality:** Centralized, secure API credentials vault with HMAC-authenticated proxy for AI tools using MCP  
**Target Users:** AI developers needing secure API key management with proxy capabilities

---

## 2. Architecture

```
┌─────────────────┐     HMAC Auth      ┌─────────────────┐     Proxy      ┌─────────────────┐
│  AI Client      │ ─────────────────► │  Keymaster MCP  │ ─────────────► │  OpenAI/Anthropic│
│  (Cursor/Claude)│     signature      │  Server         │   forward      │  API            │
└─────────────────┘                    └─────────────────┘                └─────────────────┘
                                                │
                                        ┌───────▼────────┐
                                        │  keymaster     │
                                        │  Vault (enc.)  │
                                        └────────────────┘
```

---

## 3. Project Structure

```
keymasterMCP/
├── .env.example
├── .gitignore
├── docker-compose.yml
├── Dockerfile
├── pyproject.toml
├── README.md
├── keymaster_mcp/
│   ├── __init__.py
│   ├── main.py                 # FastAPI entry point
│   ├── config.py              # Configuration management
│   ├── vault.py               # keymaster vault layer
│   ├── auth.py                # HMAC authentication middleware
│   ├── proxy.py               # Streaming proxy engine
│   ├── mcp_server.py          # MCP server implementation
│   ├── models.py              # Pydantic models
│   ├── database.py            # SQLite client tracking
│   └── cli.py                  # CLI for key management
└── tests/
    └── test_auth.py
```

---

## 4. Functionality Specification

### 4.1 Secret Vault (keymaster)

- Use `keymaster` PyPI package for encrypted local secret storage
- Manage Root Keys for: OpenAI, Anthropic, GitHub
- Keys encrypted at rest using keymaster's encryption
- CLI commands to add/rotate/list/remove keys

### 4.2 HMAC Authentication

- Time-based HMAC signature verification
- Required headers:
  - `X-Timestamp`: Unix timestamp (must be within 30 seconds)
  - `X-Signature`: HMAC-SHA256 of `{method}:{path}:{timestamp}:{body}`
  - `X-Client-Id`: Client identifier
- Reject requests with stale timestamps (replay attack prevention)

### 4.3 Proxy Engine

- Intercept incoming LLM requests at `/v1/chat/completions`
- Authenticate via HMAC middleware
- Retrieve correct Root Key from Vault
- Forward request to external provider (OpenAI-compatible)
- Stream SSE responses transparently (non-buffered)

### 4.4 MCP Integration

- Expose vault capabilities as MCP Tools
- Tools:
  - `list_services`: List available API services
  - `get_service_status`: Check if service key is configured
  - `list_clients`: List registered clients
- Compatible with Cursor, Claude Desktop

---

## 5. API Endpoints

### Proxy Endpoints
- `POST /v1/chat/completions` - Proxy to OpenAI (streaming supported)
- `POST /v1/completions` - Proxy to OpenAI completions

### Management Endpoints
- `GET /health` - Health check
- `GET /api/services` - List available services
- `GET /api/clients` - List registered clients (HMAC protected)

### MCP Endpoint
- `GET /mcp` - SSE endpoint for MCP communication

---

## 6. Database Schema (SQLite)

### clients table
```sql
CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id TEXT UNIQUE NOT NULL,
    client_secret TEXT NOT NULL,
    name TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP
);
```

---

## 7. Configuration

### Environment Variables
- `KEYMASTER_VAULT_PATH`: Path to keymaster vault (default: `./vault`)
- `DATABASE_PATH`: Path to SQLite database (default: `./keymaster.db`)
- `HMAC_SECRET`: Master secret for HMAC signing
- `LOG_LEVEL`: Logging level (default: INFO)

---

## 8. Acceptance Criteria

1. ✅ Docker-compose environment starts successfully
2. ✅ Vault encrypts and stores API keys
3. ✅ HMAC middleware rejects stale timestamps
4. ✅ Proxy streams SSE responses without buffering
5. ✅ MCP tools accessible via /mcp endpoint
6. ✅ CLI can add/rotate/list/remove keys
7. ✅ Clients table tracks client metadata
