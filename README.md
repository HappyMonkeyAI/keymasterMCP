# Keymaster MCP

Secure API Credentials Vault with MCP-first ingestion and consumption (designed to replace `.env` usage for agents).

## Overview

Keymaster MCP is a secure API credentials vault designed primarily for **agent consumption** via MCP.

Core capabilities:

- **MCP-first ingestion** — Projects and keys are created automatically by agents (not via dashboard)
- **Encrypted Vault** — Fernet-encrypted storage of API keys
- **Project Isolation** — Credentials scoped to projects with local `.keymaster/` bootstrap keys
- **Consumption without `.env`** — Agents discover context and use keys via MCP tools instead of reading environment files
- **Proxy layer** — `keymaster_proxy_request` lets agents make authenticated calls without ever seeing raw secrets
- **IP Whitelisting + HMAC** — Existing security model retained

The web dashboard is now secondary. The MCP server is the primary interface.

## Key MCP Tools

### Ingestion (agents create projects & keys)
- `keymaster_init_project` — Bootstrap a new project + local `.keymaster/` key
- `keymaster_register_key` — Register a credential for a service

### Consumption (agents use keys instead of `.env`)
- `keymaster_get_current_project` — Resolve project from local `.keymaster/`
- `keymaster_get_key` — Check key existence (never returns raw secret)
- `keymaster_proxy_request` — Make authenticated API calls (recommended)

## Architecture

```
┌─────────────────┐
│   AI Agent      │
│ (Hermes/Cursor) │
└────────┬────────┘
         │ MCP
         ▼
┌──────────────────────────────┐
│      Keymaster MCP Server    │
│  - Project context (.keymaster/)
│  - Vault access
│  - Proxy with injected keys   │
└──────────────┬───────────────┘
               │
        ┌──────┴──────┐
        ▼             ▼
   Encrypted Vault   SQLite (projects, assignments)
```

## Local Project Bootstrap

When an agent runs `keymaster_init_project`, it creates:

```
your-project/
└── .keymaster/
    └── project.key          # 0600, gitignored
```

Agents in that directory automatically inherit the project context.

## Security

- `.keymaster/` is protected by explicit nginx `deny all` rules (same as `.env`)
- Raw secrets are never returned to MCP clients
- All proxy requests are authorized against project + service assignments

## Development

```bash
# Python backend
pip install -e .
keymaster-mcp
```

See `CONTEXT.md` and `AGENTS.md` for detailed operating rules and agent workflows.