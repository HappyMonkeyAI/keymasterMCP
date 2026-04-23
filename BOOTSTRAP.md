# BOOTSTRAP.md

This document outlines the steps to initialize and maintain the Long-Term Memory (LTM) system for Keymaster MCP.

## 🚀 Initializing LTM

If the `.antigravity/memories/` directory is missing or empty, follow these steps:

1. **Create Directory Structure**:
   ```bash
   mkdir -p .antigravity/memories/codebase_insights .antigravity/memories/architectural_decisions
   ```

2. **Initialize Patterns and Lessons**:
   Create a `patterns_and_lessons.md` file to track high-level wins and failures.

3. **Populate Codebase Insights**:
   For each major module (Vault, Database, Proxy), create a summary file in `codebase_insights/`.

4. **Document Architectural Decisions**:
   Use the ADR (Architectural Decision Record) pattern in `architectural_decisions/` to log why specific technologies or patterns were chosen.

## 🧠 Maintaining LTM (Agent Instructions)

Agents should treat the LTM as a "living" document:

- **Echo (Success)**: After completing a feature or fixing a bug, add a "Success" entry to `patterns_and_lessons.md`.
- **Echo (Post-Mortem)**: If a path was abandoned or failed, log the lesson learned.
- **Ripple (Relational)**: When adding a new feature, update the relevant `codebase_insights` if the architecture has changed significantly.
- **Pulse (ADR)**: If a major design decision is made (e.g., changing encryption algorithms), create a new ADR.

## 🚥 Global LTM Configuration

To enable these protocols across all sessions, ensure `system-prompt.md` is loaded into your agent's core instructions.
