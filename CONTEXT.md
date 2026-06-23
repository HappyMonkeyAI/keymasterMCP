# Keymaster MCP — Context & Operating Manual

**Source of truth hierarchy** (highest first):
1. This `CONTEXT.md`
2. `AGENTS.md` (repo-specific agent behavior)
3. `SPEC.md` + `DESIGN.md` (historical; treat as reference)
4. Live code in `keymaster_mcp/`

## Runtime assumptions
- Python 3.11+ FastAPI backend + MCP server (`keymaster_mcp/mcp_server.py`)
- SQLite for projects/clients/assignments (`keymaster.db`)
- Fernet-encrypted vault at `KEYMASTER_VAULT_PATH` (default `./vault`)
- HMAC auth (timestamp + signature) + per-project IP whitelisting
- Optional PHP dashboard (Slim) on separate port — secondary to MCP
- Docker or native run supported

## Non-negotiable rules
- **MCP-first ingestion**: New credentials and projects enter via MCP tools, not the dashboard. Dashboard is read/audit only going forward.
- Project-scoped access only. Never expose cross-project keys.
- Auto-provision per-project bootstrap key on `init` and store in local project file (`.keymaster/` or equivalent, 0600 perms).
- Never commit vault keys, client secrets, or `.keymaster/` contents.
- All writes go through authenticated MCP or HMAC-protected API; no direct DB mutation from agents.
- IP whitelist + HMAC required for any proxy or secret access.
- **`.keymaster/` protection** (HTTP-layer, your preferred method):
  ```nginx
  location ~ /\.keymaster/ {
      deny all;
      return 404;
  }
  ```
  Also add to `.gitignore`:
  ```
  .keymaster/
  ```

## Mutable state locations
- `keymaster.db` — projects, clients, assignments
- `./vault/` — encrypted keys + Fernet key (`vault/.key`)
- `.keymaster/` (per-project) — local bootstrap keys
- `credential_registry` table — service metadata

## What not to do
- Do not treat the PHP dashboard as the primary CRUD surface.
- Do not store raw secrets in env vars or repo files.
- Do not bypass HMAC/IP checks for convenience.
- Do not add new services without registering them in `credential_registry`.

## Workflow protocols
- Bootstrap a new repo/project → run MCP `keymaster_init_project` tool (creates record + local key file).
- Register a credential → MCP `keymaster_register_key` (service, key, project).
- Agents consume via MCP tools only.
- Legacy dashboard writes should be migrated to call the same MCP/API layer.

## Resolved architecture decisions
- See `docs/adr/0001-mcp-first-ingestion.md`.
- Security model (HMAC + IP + Fernet + project isolation) is retained and extended for MCP bootstrap keys.