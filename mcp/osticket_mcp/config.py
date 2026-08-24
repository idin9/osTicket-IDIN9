"""Environment-driven configuration for the osTicket MCP server."""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

DEFAULT_BASE_URL = "https://supportdesk.in.th/idin9"


def _load_dotenv(path: Path | None = None) -> None:
    """Minimal .env loader (no external dependency on python-dotenv)."""
    candidates = [
        Path.cwd() / ".env",
        Path(__file__).resolve().parent.parent / ".env",
    ]
    if path is not None:
        candidates.insert(0, Path(path))
    dotenv = next((p for p in candidates if p.is_file()), None)
    if dotenv is None:
        return
    for line in dotenv.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ[key] = value


@dataclass(frozen=True)
class Config:
    base_url: str
    api_key: str
    host: str
    port: int
    transport: str  # "streamable-http" | "sse" | "stdio"
    streamable_http_path: str
    sse_path: str
    sse_message_path: str
    request_timeout: float

    @property
    def api_base(self) -> str:
        return f"{self.base_url.rstrip('/')}/api"

    @classmethod
    def from_env(cls, dotenv_path: str | None = None) -> "Config":
        _load_dotenv(Path(dotenv_path) if dotenv_path else None)
        transport = os.environ.get("MCP_TRANSPORT", "streamable-http").lower()
        supported = {"streamable-http", "sse", "stdio"}
        if transport not in supported:
            raise SystemExit(
                f"Unsupported MCP_TRANSPORT={transport!r}; "
                f"expected one of {sorted(supported)}"
            )
        return cls(
            base_url=os.environ.get("OS_TICKET_BASE_URL", DEFAULT_BASE_URL),
            api_key=os.environ.get("OS_TICKET_API_KEY", ""),
            host=os.environ.get("MCP_HOST", "127.0.0.1"),
            port=int(os.environ.get("MCP_PORT", "8000")),
            transport=transport,
            streamable_http_path=os.environ.get("MCP_STREAMABLE_HTTP_PATH", "/mcp"),
            sse_path=os.environ.get("MCP_SSE_PATH", "/sse"),
            sse_message_path=os.environ.get("MCP_SSE_MESSAGE_PATH", "/messages/"),
            request_timeout=float(os.environ.get("OS_TICKET_TIMEOUT", "30")),
        )

    def require_api_key(self) -> str:
        if not self.api_key:
            raise SystemExit(
                "OS_TICKET_API_KEY is not set. Point it at an API key created in "
                "Admin Panel -> Manage -> API Keys."
            )
        return self.api_key