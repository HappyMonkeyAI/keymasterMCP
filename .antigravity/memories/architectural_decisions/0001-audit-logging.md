# ADR 0001: Comprehensive Audit Logging

## Status
Accepted

## Context
As Keymaster MCP matures, there is a growing need for traceability and accountability. Users need to know exactly which clients accessed which secrets and when, especially in a multi-agent environment where credentials could be misused.

## Decision
We decided to implement a centralized audit logging system within the SQLite database.

## Details
- A new table `audit_logs` was created.
- All proxy requests and CLI secret injections are logged.
- Logs include `client_id`, `project_id`, `service`, `action`, `ip_address`, and `status`.
- Metadata is stored as a JSON blob for flexibility.

## Consequences
- **Positive**: Increased security posture, easier debugging of failed proxy requests, and audit readiness.
- **Negative**: Minor increase in database size over time and a slight performance overhead for proxy requests (mitigated by async logging).
