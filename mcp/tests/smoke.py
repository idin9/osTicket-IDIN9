"""Smoke test for the osTicket MCP server.

Discovers the server over Streamable HTTP, lists tools, and calls each one so
the transport + tool wiring is proven. Read-only tools should succeed with a
valid OS_TICKET_API_KEY; with a dummy key the API returns 401 (still proves
the pipeline, reported as isError).

Usage:
    ./osticket_mcp/tests/smoke.py --url http://127.0.0.1:8000/mcp
"""

from __future__ import annotations

import argparse
import asyncio

from mcp import Client


async def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--url", default="http://127.0.0.1:8000/mcp")
    args = parser.parse_args()

    async with Client(args.url) as client:
        result = await client.list_tools()
        tools = result.tools
        print(f"OK: {len(tools)} tools discovered")

        expected = {
            "create_ticket", "read_ticket", "list_tickets", "update_ticket",
            "post_reply", "post_note", "merge_tickets", "run_cron",
            "list_faqs", "read_faq", "create_faq", "update_faq", "delete_faq",
            "list_categories", "read_category", "create_category",
            "update_category", "delete_category",
        }
        names = {t.name for t in tools}
        missing = expected - names
        if missing:
            print(f"FAIL: missing tools {sorted(missing)}")
            return 1

        for tool in sorted(tools, key=lambda t: t.name):
            try:
                res = await client.call_tool(tool.name, {})
            except Exception as exc:  # noqa: BLE001
                print(f"  {tool.name:18s} raised: {exc}")
                continue
            first = res.content[0] if res.content else None
            text = getattr(first, "text", "") if first else ""
            status = "error" if getattr(res, "isError", False) else "ok"
            print(f"  {tool.name:18s} {status:6s} {text.splitlines()[0][:60] if text else ''}")

        print("SMOKE TEST DONE")
        return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))