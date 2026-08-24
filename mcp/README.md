# osTicket MCP Server

Model Context Protocol (MCP) server exposing **every REST API endpoint** in
this osTicket repository as typed tools an AI agent can call — tickets, cron,
and the Knowledge Base (FAQ articles + categories).

## Endpoints exposed (18 tools)

### Tickets (`api/tickets.php`)
| Tool | HTTP endpoint | Permission |
|---|---|---|
| `create_ticket` | `POST /api/tickets.json` | Can Create Tickets |
| `read_ticket` | `GET /api/tickets/<number>.json` | Can Read Tickets |
| `list_tickets` | `GET /api/tickets` | Can Read Tickets |
| `update_ticket` | `POST /api/tickets/<number>.json` | Can Update Tickets + Mapped Staff |
| `post_reply` | `POST /api/tickets/<number>/reply.json` | Can Update Tickets + Mapped Staff |
| `post_note` | `POST /api/tickets/<number>/note.json` | Can Update Tickets + Mapped Staff |
| `merge_tickets` | `POST /api/tickets/<number>/merge.json` | Can Update Tickets + Mapped Staff |

### Cron (`api/cron.php`)
| Tool | HTTP endpoint | Permission |
|---|---|---|
| `run_cron` | `POST /api/tasks/cron` | Can Execute Cron |

### Knowledge Base — FAQs (`api/kb.php`)
| Tool | HTTP endpoint | Permission |
|---|---|---|
| `list_faqs` | `GET /api/kb/faqs.json` | Can Read Knowledge Base |
| `read_faq` | `GET /api/kb/faqs/<id>.json` | Can Read Knowledge Base |
| `create_faq` | `POST /api/kb/faqs.json` | Can Manage Knowledge Base + Mapped Staff |
| `update_faq` | `POST /api/kb/faqs/<id>.json` | Can Manage Knowledge Base + Mapped Staff |
| `delete_faq` | `DELETE /api/kb/faqs/<id>.json` | Can Manage Knowledge Base + Mapped Staff |

### Knowledge Base — Categories (`api/kb.php`)
| Tool | HTTP endpoint | Permission |
|---|---|---|
| `list_categories` | `GET /api/kb/categories.json` | Can Read Knowledge Base |
| `read_category` | `GET /api/kb/categories/<id>.json` | Can Read Knowledge Base |
| `create_category` | `POST /api/kb/categories.json` | Can Manage Knowledge Base + Mapped Staff |
| `update_category` | `POST /api/kb/categories/<id>.json` | Can Manage Knowledge Base + Mapped Staff |
| `delete_category` | `DELETE /api/kb/categories/<id>.json` | Can Manage Knowledge Base + Mapped Staff |

## Requirements

- Python 3.10+ (this repo's venv is at `mcp/.venv`)
- An osTicket API key with the permissions listed above
  (Admin Panel → Manage → API Keys). Write operations and replies/notes require
  a **Mapped Staff** member on the key.
- The migration scripts must have been applied (see `api/README.md`):
  `setup/scripts/migrate-api-update-v1.sql`,
  `setup/scripts/migrate-api-read-v2.sql`, and
  `php setup/scripts/migrate-kb-api.php`.

## Setup

```sh
cd mcp
cp example.env .env          # then edit .env: set OS_TICKET_API_KEY
# Create the venv (if not present) and install
uv venv .venv
uv pip install --python .venv/bin/python -e .
```

## Run

Streamable-HTTP transport (default; agent connects over HTTP):

```sh
cd mcp
./.venv/bin/python -m osticket_mcp
# -> osTicket MCP listening on http://127.0.0.1:8000/mcp
```

SSE transport:

```sh
MCP_TRANSPORT=sse ./.venv/bin/python -m osticket_mcp
```

stdio transport (for agents that spawn the server as a subprocess):

```sh
MCP_TRANSPORT=stdio ./.venv/bin/python -m osticket_mcp
```

Config can also be passed as a positional path: `python -m osticket_mcp /path/to/.env`.

## Configuration

| Variable | Default | Description |
|---|---|---|
| `OS_TICKET_BASE_URL` | `https://supportdesk.in.th/idin9` | osTicket install base URL |
| `OS_TICKET_API_KEY` | *(required)* | API key created in the admin panel |
| `MCP_TRANSPORT` | `streamable-http` | `streamable-http`, `sse`, or `stdio` |
| `MCP_HOST` | `127.0.0.1` | Bind address |
| `MCP_PORT` | `8000` | Bind port |
| `MCP_STREAMABLE_HTTP_PATH` | `/mcp` | Streamable-HTTP endpoint path |
| `MCP_SSE_PATH` | `/sse` | SSE endpoint path |
| `MCP_SSE_MESSAGE_PATH` | `/messages/` | SSE message POST path |
| `OS_TICKET_TIMEOUT` | `30` | API request timeout (s) |

## Connecting an agent

Example for MCP clients that support URL-based Streamable-HTTP servers:

```
mcpServers:
  osticket:
    url: "http://127.0.0.1:8000/mcp"
```

For stdio:

```
mcpServers:
  osticket:
    command: "/path/to/osTicket-IDIN9/mcp/.venv/bin/python"
    args: ["-m", "osticket_mcp"]
    env:
      MCP_TRANSPORT: "stdio"
      OS_TICKET_API_KEY: "..."
      OS_TICKET_BASE_URL: "https://supportdesk.in.th/idin9"
```

## Testing

`tests/smoke.py` verifies the server boots and that all 18 tools can be
discovered and invoked:

```sh
cd mcp
OS_TICKET_API_KEY=dummy MCP_TRANSPORT=streamable-http MCP_PORT=8901 ./.venv/bin/python -m osticket_mcp &
./.venv/bin/python tests/smoke.py --url http://127.0.0.1:8901/mcp
```

The server requires `OS_TICKET_API_KEY` to boot. With a real key, read-only
calls succeed end-to-end; with a dummy key they still prove the
transport/tool wiring via the API's 401.
