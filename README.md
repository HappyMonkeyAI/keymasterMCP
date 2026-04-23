# Keymaster MCP

Secure API Credentials Vault and Proxy Server with Project-based Access Control.

## Overview

Keymaster MCP is a hybrid Python/PHP application that provides:

- **Encrypted Vault**: Store API keys (OpenAI, Anthropic, GitHub) securely
- **Project Isolation**: Assign credential subsets to different projects
- **IP Whitelisting**: Restrict project access by IP address
- **HMAC Authentication**: Time-based request signing with replay protection
- **MCP Integration**: Expose vault tools to AI assistants
- **Web Dashboard**: PHP frontend for easy management

## Architecture

```
┌──────────────┐     HMAC Auth      ┌─────────────────┐     Proxy      ┌─────────────────┐
│  AI Client   │ ─────────────────► │  Keymaster API  │ ─────────────► │  OpenAI/etc.    │
│  (Cursor)    │     signature      │  (Python :8000) │   forward      │  API            │
└──────────────┘                    └─────────────────┘                └─────────────────┘
                                                      │
                                             ┌────────┴────────┐
                                             │   SQLite DB     │
                                             │  (projects,     │
                                             │   clients)      │
                                             └─────────────────┘
                                                      │
                                             ┌────────▼────────┐
                                             │  Web Dashboard  │
                                             │  (PHP :8080)    │
                                             └─────────────────┘
```

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Or: Python 3.11+, PHP 8.1+

### Run with Docker

```bash
# Clone and start
git clone <repo> keymasterMCP
cd keymasterMCP
docker-compose up -d

# Access
# - API: http://localhost:9000
# - Web: http://localhost:9090
# - Health: http://localhost:9000/health
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `KEYMASTER_VAULT_PATH` | `./vault` | Encrypted key storage path |
| `DATABASE_PATH` | `./keymaster.db` | SQLite database path |
| `HMAC_SECRET` | `change-me` | HMAC signing secret |
| `ADMIN_USERNAME` | `admin` | Web dashboard login |
| `ADMIN_PASSWORD` | `admin` | Web dashboard password |

## Development

```bash
# Python backend only
pip install -e .
keymaster-mcp          # Start API server
keymaster-cli          # CLI for key management

# PHP frontend only
cd php
composer install
php -S localhost:8080 -t public
```

## Tech Stack

- **Backend**: Python 3.11+, FastAPI, SQLite
- **Frontend**: PHP 8.1+, Slim Framework
- **Security**: Fernet encryption, HMAC-SHA256
- **Protocol**: MCP (Model Context Protocol)

## License

MIT
