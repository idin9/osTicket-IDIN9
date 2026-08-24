"""Tool definitions for the osTicket MCP server.

Every endpoint registered in api/http.php is exposed as a typed MCP tool:
tickets (create/read/update/list/reply/note/merge), cron, and KB (FAQ and
category CRUD).
"""

from __future__ import annotations

from typing import Any

from .client import OsTicketClient


def _fmt(data: Any) -> str:
    import json

    if isinstance(data, (dict, list)):
        return json.dumps(data, ensure_ascii=False, indent=2)
    return str(data)


# Ticket tools ---------------------------------------------------------------
def build_ticket_tools(client: OsTicketClient) -> list[tuple[str, Any]]:
    """Return (name, async_function) pairs registered against `client`."""

    async def create_ticket(
        name: str,
        email: str,
        subject: str,
        message: str,
        topic_id: int | None = None,
        priority_id: int | None = None,
        alert: bool = True,
        autorespond: bool = True,
        source: str = "API",
        ip: str | None = None,
        attachments: list[dict[str, str]] | None = None,
        extra_fields: dict[str, Any] | None = None,
    ) -> str:
        """Open a new ticket. Returns the created ticket number."""
        data: dict[str, Any] = {
            "name": name,
            "email": email,
            "subject": subject,
            "message": message,
            "alert": alert,
            "autorespond": autorespond,
            "source": source,
        }
        if topic_id is not None:
            data["topicId"] = topic_id
        if priority_id is not None:
            data["priorityId"] = priority_id
        if ip:
            data["ip"] = ip
        data["attachments"] = attachments or []
        data.update(extra_fields or {})
        return _fmt(client.create_ticket(**data))

    async def read(
        ticket_number: str,
    ) -> str:
        """Read a ticket's number, subject, status and original message.
        Returns 403 if the ticket is closed."""
        return _fmt(client.read_ticket(ticket_number))

    async def list_tickets() -> str:
        """List all non-closed tickets (number, subject, due date, status)."""
        return _fmt(client.list_tickets())

    async def update(
        ticket_number: str,
        topic_id: int,
        sla_id: int | None = None,
        due_date: str | None = None,
        source: str | None = None,
        user_id: int | None = None,
        note: str | None = None,
        status_id: int | None = None,
        extra_fields: dict[str, Any] | None = None,
    ) -> str:
        """Update ticket metadata (topic, SLA, due date, source, status).

        `topic_id` is required by the API on every update (pass the ticket's
        current help-topic id to leave it unchanged)."""
        data: dict[str, Any] = {
            "topicId": topic_id,
            "slaId": sla_id,
            "duedate": due_date,
            "source": source,
            "user_id": user_id,
            "note": note,
            "status_id": status_id,
        }
        data.update(extra_fields or {})
        return _fmt(client.update_ticket(ticket_number, **data))

    async def reply(
        ticket_number: str,
        message: str,
        alert: bool = True,
        mime: str = "text/plain",
        attachments: list[dict[str, str]] | None = None,
    ) -> str:
        """Post an agent reply to an existing ticket. `mime` may be
        text/plain or text/html."""
        return _fmt(
            client.post_reply(
                ticket_number,
                message,
                alert=alert,
                mime=mime,
                attachments=attachments,
            )
        )

    async def note(
        ticket_number: str,
        note: str,
        title: str | None = None,
        note_status_id: int | None = None,
        attachments: list[dict[str, str]] | None = None,
    ) -> str:
        """Post an internal (staff-only) note to a ticket, optionally
        changing its status at the same time."""
        return _fmt(
            client.post_note(
                ticket_number,
                note,
                title=title,
                note_status_id=note_status_id,
                attachments=attachments,
            )
        )

    async def merge(
        parent_ticket_number: str,
        tids: list[str],
        merge_type: str = "combine",
        child_status_id: int = 3,
        parent_status_id: int = 0,
        participants: str = "all",
        delete_child: bool = False,
        move_tasks: bool = True,
    ) -> str:
        """Merge duplicate tickets into a parent. `tids` are the child
        tickets; `merge_type` is 'combine' or 'visual'."""
        return _fmt(
            client.merge_tickets(
                parent_ticket_number,
                tids,
                merge_type=merge_type,
                child_status_id=child_status_id,
                parent_status_id=parent_status_id,
                participants=participants,
                delete_child=delete_child,
                move_tasks=move_tasks,
            )
        )

    return [
        ("create_ticket", create_ticket),
        ("read_ticket", read),
        ("list_tickets", list_tickets),
        ("update_ticket", update),
        ("post_reply", reply),
        ("post_note", note),
        ("merge_tickets", merge),
    ]


# Cron tool -------------------------------------------------------------------
def build_cron_tool(client: OsTicketClient) -> list[tuple[str, Any]]:
    async def run_cron() -> str:
        """Trigger the osTicket cron job (fetch mail, run filters, alerts)."""
        return _fmt(client.run_cron())

    return [("run_cron", run_cron)]


# KB FAQ tools ----------------------------------------------------------------
def build_faq_tools(client: OsTicketClient) -> list[tuple[str, Any]]:
    async def list_faqs() -> str:
        """List all knowledge base FAQ articles."""
        return _fmt(client.list_faqs())

    async def read_faq(faq_id: int) -> str:
        """Read a single FAQ article by id."""
        return _fmt(client.read_faq(faq_id))

    async def create_faq(
        question: str,
        answer: str,
        category_id: int,
        ispublished: int = 1,
        keywords: str | None = None,
        notes: str | None = None,
        topics: list[int] | None = None,
    ) -> str:
        """Create a new knowledge base FAQ article.
        ispublished: 1=public, 2=staff-only, 3=private."""
        return _fmt(
            client.create_faq(
                question=question,
                answer=answer,
                category_id=category_id,
                ispublished=ispublished,
                keywords=keywords,
                notes=notes,
                topics=topics,
            )
        )

    async def update_faq(
        faq_id: int,
        question: str | None = None,
        answer: str | None = None,
        category_id: int | None = None,
        ispublished: int | None = None,
        keywords: str | None = None,
        notes: str | None = None,
        topics: list[int] | None = None,
    ) -> str:
        """Update an existing FAQ article. Omitted fields keep their values."""
        return _fmt(
            client.update_faq(
                faq_id,
                question=question,
                answer=answer,
                category_id=category_id,
                ispublished=ispublished,
                keywords=keywords,
                notes=notes,
                topics=topics,
            )
        )

    async def delete_faq(faq_id: int) -> str:
        """Delete a knowledge base FAQ article."""
        return _fmt(client.delete_faq(faq_id))

    return [
        ("list_faqs", list_faqs),
        ("read_faq", read_faq),
        ("create_faq", create_faq),
        ("update_faq", update_faq),
        ("delete_faq", delete_faq),
    ]


# KB category tools -----------------------------------------------------------
def build_category_tools(client: OsTicketClient) -> list[tuple[str, Any]]:
    async def list_categories() -> str:
        """List all knowledge base categories."""
        return _fmt(client.list_categories())

    async def read_category(category_id: int) -> str:
        """Read a KB category, its child categories, and its FAQs."""
        return _fmt(client.read_category(category_id))

    async def create_category(
        name: str,
        description: str | None = None,
        ispublic: int = 1,
        pid: int = 0,
        notes: str | None = None,
    ) -> str:
        """Create a knowledge base category. ispublic: 1=public, 2=staff-only,
        3=private."""
        return _fmt(
            client.create_category(
                name=name,
                description=description,
                ispublic=ispublic,
                pid=pid,
                notes=notes,
            )
        )

    async def update_category(
        category_id: int,
        name: str | None = None,
        description: str | None = None,
        ispublic: int | None = None,
        pid: int | None = None,
        notes: str | None = None,
    ) -> str:
        """Update an existing KB category. Omitted fields keep their values."""
        return _fmt(
            client.update_category(
                category_id,
                name=name,
                description=description,
                ispublic=ispublic,
                pid=pid,
                notes=notes,
            )
        )

    async def delete_category(category_id: int) -> str:
        """Delete a KB category (fails if it still contains FAQs or children)."""
        return _fmt(client.delete_category(category_id))

    return [
        ("list_categories", list_categories),
        ("read_category", read_category),
        ("create_category", create_category),
        ("update_category", update_category),
        ("delete_category", delete_category),
    ]


# Kanban (Tasks board) tools --------------------------------------------------
def build_kanban_tools(client: OsTicketClient) -> list[tuple[str, Any]]:
    async def list_board(
        status: str | None = None,
        dept_id: int | None = None,
        team_id: int | None = None,
        assignee: str | None = None,
        due: str | None = None,
        q: str | None = None,
    ) -> str:
        """Return the Task Kanban board with cards grouped by status column."""
        return _fmt(
            client.list_kanban_board(
                status=status,
                dept_id=dept_id,
                team_id=team_id,
                assignee=assignee,
                due=due,
                q=q,
            )
        )

    async def list_columns() -> str:
        """List the Kanban board columns (statuses) available for tasks."""
        return _fmt(client.list_kanban_columns())

    async def move_card(
        task_id: int,
        status: str,
        assignee: str | None = None,
        comments: str | None = None,
    ) -> str:
        """Move a Task card to another column (status) and optionally reassign.

        `status` is one of open, doing, verifying, or closed. `assignee` is an
        id like 's12' (staff) or 't5' (team)."""
        return _fmt(
            client.move_kanban_card(
                task_id,
                status,
                assignee=assignee,
                comments=comments,
            )
        )

    async def create_task(
        title: str,
        dept_id: int | None = None,
        duedate: str | None = None,
        assignee: str | None = None,
        description: str | None = None,
    ) -> str:
        """Create a new Task from the Kanban board. Returns the new card."""
        return _fmt(
            client.create_kanban_task(
                title,
                dept_id=dept_id,
                duedate=duedate,
                assignee=assignee,
                description=description,
            )
        )

    async def update_task(
        task_id: int,
        status: str | None = None,
        assignee: str | None = None,
        dept_id: int | None = None,
        duedate: str | None = None,
        title: str | None = None,
    ) -> str:
        """Update an existing Task from the Kanban board."""
        return _fmt(
            client.update_kanban_task(
                task_id,
                status=status,
                assignee=assignee,
                dept_id=dept_id,
                duedate=duedate,
                title=title,
            )
        )

    return [
        ("list_kanban_board", list_board),
        ("list_kanban_columns", list_columns),
        ("move_kanban_card", move_card),
        ("create_kanban_task", create_task),
        ("update_kanban_task", update_task),
    ]


def register_all(client: OsTicketClient, server: Any) -> None:
    """Register every osTicket API endpoint as an MCP tool."""
    tool_specs = (
        build_ticket_tools(client)
        + build_cron_tool(client)
        + build_faq_tools(client)
        + build_category_tools(client)
        + build_kanban_tools(client)
    )
    for name, fn in tool_specs:
        server.add_tool(fn, name=name)