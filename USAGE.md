# USAGE.md

## Complete Usage Guide

### 1. Initial Setup

#### Start the Services

```bash
docker-compose up -d
```

This starts:
- Python API on port 9000
- PHP Frontend on port 9090

#### First Login

1. Open http://localhost:9090
2. Login with admin/admin (or configured credentials)
3. The frontend will auto-create a client on first use

### 2. Adding API Credentials

#### Via Web Dashboard

1. Go to **Credentials** in the navigation
2. Click **Add Key** next to a service
3. Enter your API key (e.g., `sk-...` for OpenAI)
4. Click **Add**

#### Via CLI

```bash
keymaster-cli vault add openai sk-your-key
keymaster-cli vault add anthropic sk-ant-your-key
keymaster-cli vault add github ghp_your-token
```

#### Via API

```bash
# First create a client
curl -X POST http://localhost:9000/api/clients \
  -H "Content-Type: application/json" \
  -d '{"client_id": "my-client", "name": "My Client"}'

# Then add keys (requires HMAC auth - see below)
```

### 3. Creating Projects

Projects let you:
- Assign specific credentials to each project
- Whitelist IP addresses
- Generate unique API keys per project

#### Via Web Dashboard

1. Go to **Projects**
2. Click **New Project**
3. Enter name and description
4. Save

#### Assign Credentials

1. Click on a project to view details
2. Under **Credentials**, select services from dropdown and click **Add**
3. Only services with configured keys will appear

#### Add IP Whitelist

1. Under **IP Whitelist**, enter an IP address (e.g., `192.168.1.100`)
2. Click **Add IP**
3. Requests from non-whitelisted IPs will be rejected

### 4. Using the Proxy

#### Authentication

All proxy requests require HMAC authentication:

```python
import hmac
import hashlib
import time

def sign_request(method, path, body, client_secret):
    timestamp = int(time.time())
    message = f"{method}:{path}:{timestamp}:{body}"
    signature = hmac.new(
        client_secret.encode(),
        message.encode(),
        hashlib.sha256
    ).hexdigest()
    return {
        "X-Client-Id": "your-client-id",
        "X-Timestamp": str(timestamp),
        "X-Signature": signature
    }
```

#### Example: OpenAI Chat Completion

```python
import requests
import json

# Your project credentials
CLIENT_ID = "project-1"
CLIENT_SECRET = "your-project-secret"  # From project creation
API_URL = "http://localhost:9000"

# Build request
payload = {
    "model": "gpt-4",
    "messages": [{"role": "user", "content": "Hello!"}]
}
body = json.dumps(payload)

# Sign request
headers = sign_request("POST", "/v1/chat/completions", body, CLIENT_SECRET)
headers["Content-Type"] = "application/json"

# Send
response = requests.post(
    f"{API_URL}/v1/chat/completions",
    data=body,
    headers=headers,
    stream=True
)

# Handle streaming response
for line in response.iter_lines():
    if line:
        print(line.decode())
```

#### Example: cURL

```bash
# Create signature
TIMESTAMP=$(date +%s)
BODY='{"model":"gpt-4","messages":[{"role":"user","content":"Hello"}]}'
MESSAGE="POST:/v1/chat/completions:${TIMESTAMP}:${BODY}"
SIGNATURE=$(echo -n "$MESSAGE" | openssl dgst -sha256 -hmac "your-client-secret" | cut -d' ' -f2)

# Make request
curl -X POST http://localhost:9000/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "X-Client-Id: your-client-id" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Signature: $SIGNATURE" \
  -d "$BODY"
```

### 5. MCP Integration

#### Claude Desktop Configuration

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "keymaster-vault": {
      "command": "python",
      "args": ["-m", "keymaster_mcp.mcp_server"]
    }
  }
}
```

#### Available Tools

- `list_services` - List available API services
- `get_service_status` - Check if service is configured
- `list_clients` - List registered clients
- `get_vault_info` - Get vault information

### 6. Troubleshooting

#### "Invalid signature" Error

1. Check client_id matches
2. Verify client_secret is correct
3. Ensure timestamp is within 30 seconds

#### "Request timestamp expired"

1. Check server clock is synchronized
2. Request may have taken too long - retry

#### "IP address not whitelisted"

1. Add your IP to the project whitelist
2. Or remove IP restrictions if not needed

#### "Service not configured"

1. Add API key for the service in Credentials
2. Make sure key is saved correctly

### 7. Security Best Practices

1. **Never commit secrets** - Use environment variables
2. **Rotate keys regularly** - Use the rotate function
3. **Restrict IPs** - Whitelist specific IPs per project
4. **Use HTTPS** - In production, proxy through nginx/traefik
5. **Monitor logs** - Check for unauthorized access attempts

### 8. API Reference

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Health check |
| `/api/services` | GET | List available services |
| `/api/keys` | POST | Add API key |
| `/api/keys/{service}` | DELETE | Delete API key |
| `/api/projects` | GET/POST | List/Create projects |
| `/api/projects/{id}` | GET/PUT/DELETE | Project CRUD |
| `/api/projects/{id}/credentials` | POST | Add credential |
| `/api/projects/{id}/ips` | POST | Add IP whitelist |
| `/v1/chat/completions` | POST | Proxy to OpenAI |
| `/v1/completions` | POST | Proxy to OpenAI completions |
