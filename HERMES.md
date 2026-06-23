# Keymaster MCP — Hermes / Agent Workflow Guidance

**Core identity**: Security-first credential vault with MCP as the primary ingestion and access layer.

## Agent behavior rules
- Always follow `CONTEXT.md` non-negotiables.
- Prefer MCP tool calls over direct API or DB access when acting as a client.
- When initializing a project, use the MCP bootstrap flow (never manual DB inserts).
- After any successful verification or test, follow the Ratchet protocol from `AGENTS.md` (auto `git add && git commit`).
- Use Trinity lenses (Echo / Ripple / Pulse) when making structural changes.
- Record durable decisions in `docs/adr/`.

## MCP tool usage expectations
- All credential registration and project creation must go through the new MCP ingestion tools (to be defined after bootstrap).
- Local project keys in `.keymaster/` are the per-project auth material for agents.
- Never log or echo raw secrets.

## Verification & safety
- Run `pytest` + manual HMAC/IP tests after changes.
- Pulse Reset on repeated failures.
- Keep the PHP dashboard in sync only via the shared API layer.

## Long-term memory
- `.antigravity/memories/` remains the LTM store (as defined in AGENTS.md).
- Update `patterns_and_lessons.md` after any non-trivial win or correction.