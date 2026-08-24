"""osTicket MCP server.

Exposes every REST API endpoint of this osTicket instance as MCP tools so an
AI agent can create/read/update tickets, run cron, and manage the knowledge
base (FAQ articles and categories).

Run as a Streamable-HTTP or SSE server::

    OS_TICKET_BASE_URL=https://support.example/  \\
    OS_TICKET_API_KEY=<your-api-key>  \\
    python -m osticket_mcp
"""

__version__ = "1.0.0"