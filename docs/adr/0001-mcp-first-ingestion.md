# ADR 0001: MCP as Primary Ingestion Path for Credentials

**Date**: 2026-06-23  
**Status**: Accepted  
**Deciders**: Stephen Phillips + Hermes

## Context
Keymaster MCP was originally designed with a PHP web dashboard as the main surface for creating projects and grouping credentials. After discussion around local agent usage and the desire for automatic registration during project work, the decision was made to invert the priority.

## Decision
Make the MCP server the authoritative and primary point of ingestion for projects and credentials. Agents register keys automatically via MCP tools. The web dashboard becomes a secondary / read-only or audit interface.

Project initialization will auto-provision a scoped bootstrap key stored in a local project file (`.keymaster/`) that the MCP server can use for that working directory.

Security model (existing HMAC + per-project IP whitelisting + Fernet vault + client secrets) is extended rather than replaced.

## Consequences
- New MCP tools required: `keymaster_init_project`, `keymaster_register_key`, `keymaster_list_project_keys`, etc.
- Dashboard controllers should eventually call the same MCP/API layer for writes.
- Local `.keymaster/` files become part of the per-repo secret surface (must be gitignored and 0600).
- Reduces human friction for agents while preserving all existing security primitives.
- Requires updates to `CONTEXT.md`, `HERMES.md`, and `mcp_server.py`.

## Alternatives considered
- Keep dashboard as primary and add optional MCP sync layer — rejected because it keeps the human UI as the bottleneck.
- Pure CLI-only ingestion — rejected because MCP is the native protocol for the target consumers (agents).