"""HTTP client for the osTicket REST API (tickets, cron, and KB)."""

from __future__ import annotations

from typing import Any

import httpx

ERROR_TEXT = {
    400: "Bad request - invalid or missing data",
    401: "Unauthorized - missing/invalid API key or insufficient permissions",
    403: "Forbidden - key lacks permission, or ticket is closed",
    404: "Not found",
    415: "Unsupported content type",
    500: "Internal server error",
}


def to_rfc2397(text: str, mime: str = "text/plain") -> str:
    """Encode a plain string as an RFC 2397 data URI (what the API expects)."""
    if text.startswith("data:"):
        return text
    return f"data:{mime},{text}"


def build_attachments(
    attachments: list[dict[str, str]] | None,
) -> list[dict[str, str]]:
    """Normalize attachments to the {filename: data-uri} shape the API wants."""
    if not attachments:
        return []
    out = []
    for entry in attachments:
        if "data" in entry and "name" in entry:
            encoded = entry["data"]
            if not encoded.startswith("data:"):
                encoded = f"data:{entry.get('type', 'application/octet-stream')};base64,{encoded}"
            out.append({entry["name"]: encoded})
        elif "filename" in entry and "contents" in entry:
            data = entry["contents"]
            if not data.startswith("data:"):
                data = f"data:{entry.get('type', 'text/plain')},{data}"
            out.append({entry["filename"]: data})
    return out


class OsTicketApiError(RuntimeError):
    def __init__(self, status: int, body: str) -> None:
        self.status = status
        self.body = body
        super().__init__(f"{status} {ERROR_TEXT.get(status, 'error')}: {body.strip()[:500]}")


class OsTicketClient:
    """Thin wrapper over every endpoint exposed in api/http.php."""

    def __init__(self, api_base: str, api_key: str, timeout: float = 30.0) -> None:
        self.api_base = api_base.rstrip("/")
        self.headers = {
            "X-API-Key": api_key,
            "Accept": "application/json",
        }
        self._client = httpx.Client(headers=self.headers, timeout=timeout)

    def close(self) -> None:
        self._client.close()

    def __enter__(self) -> "OsTicketClient":
        return self

    def __exit__(self, *exc: Any) -> None:
        self.close()

    # -- low level -------------------------------------------------------
    def _request(self, method: str, path: str, *, json: dict[str, Any] | None = None) -> Any:
        url = f"{self.api_base}/{path.lstrip('/')}"
        resp = self._client.request(method, url, json=json)
        return self._handle(resp)

    def _handle(self, resp: httpx.Response) -> Any:
        if resp.status_code >= 400:
            raise OsTicketApiError(resp.status_code, resp.text)
        content_type = resp.headers.get("content-type", "")
        if "application/json" in content_type:
            try:
                return resp.json()
            except ValueError:
                pass
        text = resp.text.strip()
        # Create/update/reply/note endpoints return a bare ticket number.
        return {"status": resp.status_code, "body": text}

    # -- tickets ----------------------------------------------------------
    def create_ticket(self, **data: Any) -> Any:
        payload = dict(data)
        if payload.get("message"):
            payload["message"] = to_rfc2397(str(payload["message"]))
        attachments = build_attachments(payload.pop("attachments", None))
        if attachments:
            payload["attachments"] = attachments
        return self._request("POST", "tickets.json", json=self._drop_empty(payload))

    def update_ticket(self, number: str, **data: Any) -> Any:
        payload = self._drop_empty(data)
        return self._request("POST", f"tickets/{number}.json", json=payload)

    def read_ticket(self, number: str) -> Any:
        return self._request("GET", f"tickets/{number}.json")

    def list_tickets(self) -> Any:
        return self._request("GET", "tickets")

    def post_reply(
        self,
        number: str,
        message: str,
        *,
        alert: bool = True,
        mime: str = "text/plain",
        attachments: list[dict[str, str]] | None = None,
    ) -> Any:
        payload: dict[str, Any] = {
            "alert": alert,
            "message": to_rfc2397(message, mime),
        }
        if attachments:
            payload["attachments"] = build_attachments(attachments)
        return self._request("POST", f"tickets/{number}/reply.json", json=payload)

    def post_note(
        self,
        number: str,
        note: str,
        *,
        title: str | None = None,
        note_status_id: int | None = None,
        attachments: list[dict[str, str]] | None = None,
    ) -> Any:
        # The API's JSON parser only decodes RFC 2397 data URIs for the
        # `message` key, not `note` -- send the note as plain text or the
        # "data:text/plain," prefix is stored literally in the ticket.
        payload: dict[str, Any] = {"note": note}
        if title:
            payload["title"] = title
        if note_status_id is not None:
            payload["note_status_id"] = note_status_id
        if attachments:
            payload["attachments"] = build_attachments(attachments)
        return self._request("POST", f"tickets/{number}/note.json", json=payload)

    def merge_tickets(
        self,
        parent_number: str,
        tids: list[str],
        *,
        merge_type: str = "combine",
        child_status_id: int = 3,
        parent_status_id: int = 0,
        participants: str = "all",
        delete_child: bool = False,
        move_tasks: bool = True,
    ) -> Any:
        payload = {
            "tids": tids,
            "merge_type": merge_type,
            "child_status_id": child_status_id,
            "parent_status_id": parent_status_id,
            "participants": participants,
            "delete_child": delete_child,
            "move_tasks": move_tasks,
        }
        return self._request("POST", f"tickets/{parent_number}/merge.json", json=payload)

    # -- cron ---------------------------------------------------------------
    def run_cron(self) -> Any:
        resp = self._client.post(f"{self.api_base}/tasks/cron")
        return self._handle(resp)

    # -- Kanban (Tasks board) -----------------------------------------------
    def list_kanban_board(
        self,
        *,
        status: str | None = None,
        dept_id: int | None = None,
        team_id: int | None = None,
        assignee: str | None = None,
        due: str | None = None,
        q: str | None = None,
    ) -> Any:
        """Return the Task Kanban board grouped by status column."""
        params: dict[str, Any] = {}
        if status is not None:
            params["status"] = status
        if dept_id is not None:
            params["dept_id"] = dept_id
        if team_id is not None:
            params["team_id"] = team_id
        if assignee is not None:
            params["assignee"] = assignee
        if due is not None:
            params["due"] = due
        if q is not None:
            params["q"] = q
        resp = self._client.get(f"{self.api_base}/tasks/kanban.json", params=params)
        return self._handle(resp)

    def list_kanban_columns(self) -> Any:
        """List the available Kanban board columns (statuses)."""
        resp = self._client.get(f"{self.api_base}/tasks/kanban/statuses.json")
        return self._handle(resp)

    def move_kanban_card(
        self,
        task_id: int,
        status: str,
        *,
        assignee: str | None = None,
        comments: str | None = None,
    ) -> Any:
        """Move a Task card to another column and optionally reassign it.

        `status` is one of open, doing, verifying, or closed.
        """
        payload: dict[str, Any] = {
            "id": task_id,
            "status": status,
        }
        if assignee is not None:
            payload["assignee"] = assignee
        if comments is not None:
            payload["comments"] = comments
        return self._request("POST", "tasks/kanban/move.json", json=payload)

    def create_kanban_task(
        self,
        title: str,
        *,
        dept_id: int | None = None,
        duedate: str | None = None,
        assignee: str | None = None,
        description: str | None = None,
    ) -> Any:
        """Create a new Task from the Kanban board."""
        payload: dict[str, Any] = {"title": title}
        if dept_id is not None:
            payload["dept_id"] = dept_id
        if duedate is not None:
            payload["duedate"] = duedate
        if assignee is not None:
            payload["assignee"] = assignee
        if description is not None:
            payload["description"] = description
        return self._request("POST", "tasks.json", json=self._drop_empty(payload))

    def update_kanban_task(
        self,
        task_id: int,
        *,
        status: str | None = None,
        assignee: str | None = None,
        dept_id: int | None = None,
        duedate: str | None = None,
        title: str | None = None,
    ) -> Any:
        """Update an existing Task from the Kanban board."""
        payload: dict[str, Any] = self._drop_empty(
            {
                "status": status,
                "assignee": assignee,
                "dept_id": dept_id,
                "duedate": duedate,
                "title": title,
            }
        )
        return self._request(
            "POST", f"tasks/{task_id}.json", json=payload
        )

    # -- KB FAQ -------------------------------------------------------------
    def list_faqs(self) -> Any:
        return self._request("GET", "kb/faqs.json")

    def read_faq(self, faq_id: int) -> Any:
        return self._request("GET", f"kb/faqs/{faq_id}.json")

    def create_faq(self, **data: Any) -> Any:
        payload = self._drop_empty(data)
        return self._request("POST", "kb/faqs.json", json=payload)

    def update_faq(self, faq_id: int, **data: Any) -> Any:
        return self._request("POST", f"kb/faqs/{faq_id}.json", json=self._drop_empty(data))

    def delete_faq(self, faq_id: int) -> Any:
        return self._request("DELETE", f"kb/faqs/{faq_id}.json")

    # -- KB categories ------------------------------------------------------
    def list_categories(self) -> Any:
        return self._request("GET", "kb/categories.json")

    def read_category(self, category_id: int) -> Any:
        return self._request("GET", f"kb/categories/{category_id}.json")

    def create_category(self, **data: Any) -> Any:
        return self._request("POST", "kb/categories.json", json=self._drop_empty(data))

    def update_category(self, category_id: int, **data: Any) -> Any:
        return self._request("POST", f"kb/categories/{category_id}.json", json=self._drop_empty(data))

    def delete_category(self, category_id: int) -> Any:
        return self._request("DELETE", f"kb/categories/{category_id}.json")

    @staticmethod
    def _drop_empty(payload: dict[str, Any]) -> dict[str, Any]:
        return {k: v for k, v in payload.items() if v is not None}