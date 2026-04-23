# Backend Architecture Insight

The Keymaster MCP backend is built using FastAPI and follows a modular design focused on security and transparency.

## Core Modules

- **Vault (`vault.py`)**: 
    - Uses Fernet symmetric encryption (`cryptography` library).
    - Stores API keys in a single encrypted file (`keys.enc`).
    - Supports environment-specific keys using `service:environment` notation.
    - The vault key is stored in a separate `.key` file with `0o600` permissions.

- **Database (`database.py`)**:
    - SQLite database managed via `aiosqlite`.
    - Handles client registration, project management, and IP whitelisting.
    - Includes an `audit_logs` table for tracking all secret-related actions.

- **Proxy Engine (`proxy.py`)**:
    - An `httpx`-based streaming proxy.
    - Injects API keys from the vault into outgoing requests to supported services (e.g., OpenAI).
    - Logs every request to the `audit_logs` for traceability.

- **CLI (`cli.py`)**:
    - A `click`-based command-line interface.
    - Provides administrative tools for vault and client management.
    - Features a `run` command for executing local processes with injected secrets.

## Interaction Flow
1. Client makes a request to `/v1/chat/completions` with HMAC auth.
2. `HMACAuth` middleware verifies the signature.
3. `ProxyEngine` retrieves the `openai` key from `Vault`.
4. `ProxyEngine` logs the attempt in `Database`.
5. `ProxyEngine` forwards the request to OpenAI and streams the response back.
