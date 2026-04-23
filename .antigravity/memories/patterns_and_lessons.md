# Patterns and Lessons

## [2026-04-23] Infisical Integration Patterns

### Success: CLI Secret Injection
Implementing a `run` command that wraps subprocesses and injects environment variables is a highly effective way to bridge the gap between static secret storage and dynamic application needs. It mimics the successful pattern used by Infisical.

### Lesson: Async Logging in Sync CLI
When adding async logging to a Click-based CLI, ensure the event loop is handled correctly. Using `asyncio.run()` or `loop.create_task()` depending on the state of the loop is necessary to avoid "RuntimeError: Event loop is closed" errors.

### Pattern: Service-to-Env Mapping
Explicitly mapping services (like `openai`) to their standard environment variables (`OPENAI_API_KEY`) is essential for a "zero-config" experience for developers.
