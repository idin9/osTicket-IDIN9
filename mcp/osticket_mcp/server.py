"""Server entry point: build the MCPServer and run it over the chosen transport."""

from __future__ import annotations

import sys

from .client import OsTicketClient
from .config import Config
from .tools import register_all


def build_server(config: Config) -> tuple[Any, OsTicketClient]:
    from mcp.server.mcpserver import MCPServer

    key = config.require_api_key()
    client = OsTicketClient(config.api_base, key, timeout=config.request_timeout)

    server = MCPServer(
        name="osticket",
        title="osTicket API",
        description=(
            "osTicket REST API: create, read, update, merge tickets; post "
            "replies and internal notes; run cron; manage the Knowledge Base "
            "FAQ articles and categories; and manage the Tasks Kanban board "
            "(list boards/columns, move, create, and update task cards)."
        ),
        version="1.0.0",
    )
    register_all(client, server)
    return server, client


def run(config: Config) -> None:
    server, client = build_server(config)
    try:
        if config.transport == "stdio":
            server.run("stdio")
        elif config.transport == "sse":
            server.run(
                "sse",
                sse_path=config.sse_path,
                message_path=config.sse_message_path,
            )
        else:
            server.run(
                "streamable-http",
                host=config.host,
                port=config.port,
                streamable_http_path=config.streamable_http_path,
            )
    except KeyboardInterrupt:
        pass
    finally:
        client.close()


def main(argv: list[str] | None = None) -> None:
    argv = argv if argv is not None else sys.argv[1:]
    dotenv_path = None
    if argv and not argv[0].startswith("-"):
        dotenv_path = argv[0]
        argv = argv[1:]
    config = Config.from_env(dotenv_path)
    if config.transport != "stdio":
        print(
            f"osTicket MCP listening on http://{config.host}:{config.port}"
            + (
                config.streamable_http_path
                if config.transport == "streamable-http"
                else f" (SSE at {config.sse_path}, messages at {config.sse_message_path})"
            ),
            file=sys.stderr,
            flush=True,
        )
    run(config)


if __name__ == "__main__":
    main()