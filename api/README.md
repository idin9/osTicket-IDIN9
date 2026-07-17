# osTicket API Usage (v1.18.4.02)

## Authentication

All API requests require an `X-API-Key` header with a valid API key registered in the system.

## API Key Configuration

API keys must have the following permissions enabled:
- `can_create_tickets` — Create new tickets
- `can_update_tickets` — Update existing tickets, post replies, and post internal notes
- `can_exec_cron` — Execute cron tasks

For ticket updates, replies, and internal notes, the API key **must be associated with a staff member** via the **Mapped Staff** dropdown in the admin panel (`Admin Panel → Manage → API Keys → Edit`).

### Admin GUI

When editing an API key in the admin panel:

1. **Can Update Tickets** checkbox — grants permission to call `update`, `reply`, and `note` endpoints
2. **Mapped Staff** dropdown — selects which staff member the API acts as (required for update/reply/note endpoints)

Both are available on the **Add** and **Edit** API Key forms.

### Migration for Existing Installations

Run the migration script to add the new columns to existing installations:

```sql
-- File: setup/scripts/migrate-api-update-v1.sql
ALTER TABLE `%TABLE_PREFIX%api_key`
  ADD COLUMN `can_update_tickets` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `can_exec_cron`,
  ADD COLUMN `staff_id` int(10) unsigned NOT NULL DEFAULT '0' AFTER `can_update_tickets`;
```

Replace `%TABLE_PREFIX%` with your actual table prefix (default is `ost_`).

---

## Endpoints

### 1. Create Ticket

```
POST /api/tickets.(xml|json)
```

**Request body (JSON):**
```json
{
  "alert": true,
  "autorespond": true,
  "source": "API",
  "topicId": 1,
  "priorityId": 2,
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Test ticket",
  "message": "data:text/plain,Hello this is a test ticket",
  "attachments": [
    {
      "file.txt": "data:text/plain;base64,SGVsbG8gV29ybGQ="
    }
  ],
  "ip": "192.168.0.1"
}
```

**Response:** `201 Created` — Returns ticket number
```
201
201234
```

---

### 2. Update Ticket

```
POST /api/tickets/<number>.(xml|json)
```

Updates ticket metadata (topic, SLA, due date, source, status).

**Note:** `<number>` is the visible ticket number (e.g. `201234`), not the internal database ID.

**Request body (JSON):**
```json
{
  "topicId": 2,
  "slaId": 1,
  "duedate": "2026-12-31",
  "source": "Phone",
  "user_id": 5,
  "note": "Updated via API",
  "status_id": 3
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `topicId` | int | Yes | Help topic ID |
| `slaId` | int | No | SLA ID |
| `duedate` | string | No | Due date (YYYY-MM-DD) |
| `source` | string | No | Ticket source |
| `user_id` | int | No | Assign ticket to user |
| `note` | string | No | Internal change note |
| `status_id` | int | No | New ticket status ID (e.g. 2=Open, 3=Closed) |

**Response:** `200 OK` — Returns ticket number
```
200
201234
```

---

### 3. Post Reply to Ticket

```
POST /api/tickets/<number>/reply.(xml|json)
```

Posts an agent response to an existing ticket.

**Note:** `<number>` is the visible ticket number (e.g. `201234`), not the internal database ID.

**Request body (JSON):**
```json
{
  "alert": true,
  "message": "data:text/plain,This is an agent response",
  "attachments": [
    {
      "screenshot.png": "data:image/png;base64,iVBORw0KGgo..."
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `message` | string | Yes | Reply body in RFC 2397 format |
| `alert` | bool | No | Send email alert (default: true) |
| `attachments` | array | No | File attachments |

**Response:** `201 Created` — Returns ticket number
```
201
201234
```

---

### 4. Post Internal Note

```
POST /api/tickets/<number>/note.(xml|json)
```

Posts an internal note to an existing ticket. Optionally changes ticket status at the same time via `note_status_id`.

**Note:** `<number>` is the visible ticket number (e.g. `201234`), not the internal database ID.

**Request body (JSON):**
```json
{
  "note": "data:text/plain,Investigated the issue — root cause identified",
  "title": "Investigation notes",
  "note_status_id": 3,
  "attachments": [
    {
      "debug.log": "data:text/plain;base64,RG9udCB0ZWxsIG15IGJvc3M="
    }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `note` | string | Yes | Note body in RFC 2397 format |
| `title` | string | No | Note title |
| `note_status_id` | int | No | New ticket status ID (e.g. 2=Open, 3=Closed) |
| `attachments` | array | No | File attachments |

**Response:** `201 Created` — Returns ticket number
```
201
201234
```

---

### 5. Read Ticket

```
GET /api/tickets/<number>.(xml|json)
```

Reads a ticket's details and original message. Returns `403 Forbidden` if the ticket is closed.

**Note:** `<number>` is the visible ticket number (e.g. `201234`), not the internal database ID.

**Response:** `200 OK` — Returns ticket details

**JSON:**
```json
{
  "number": "202407-0042",
  "subject": "Cannot access email server after upgrade",
  "status": {
    "id": 2,
    "name": "Open",
    "state": "open"
  },
  "created": "2026-07-16 09:42:00",
  "original_message": {
    "body": "Hi, I can't access the company email since this morning's upgrade.",
    "date": "2026-07-16 09:42:00",
    "poster": "Jane Doe"
  }
}
```

**XML:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<ticket>
  <number>202407-0042</number>
  <subject>Cannot access email server after upgrade</subject>
  <status>
    <id>2</id>
    <name>Open</name>
    <state>open</state>
  </status>
  <created>2026-07-16 09:42:00</created>
  <original_message>
    <body>Hi, I can't access the company email since this morning's upgrade.</body>
    <date>2026-07-16 09:42:00</date>
    <poster>Jane Doe</poster>
  </original_message>
</ticket>
```

| Field | Type | Description |
|---|---|---|
| `number` | string | Ticket number |
| `subject` | string | Ticket subject |
| `status.id` | int | Status ID |
| `status.name` | string | Status name |
| `status.state` | string | Status state (open, closed, etc.) |
| `created` | string | Ticket creation date |
| `original_message.body` | string | Original message body |
| `original_message.date` | string | Message creation date |
| `original_message.poster` | string | Message author |

---

## Error Responses

| Code | Meaning |
|---|---|
| 400 | Bad request — invalid or missing data |
| 401 | Unauthorized — missing/invalid API key or insufficient permissions |
| 403 | Forbidden — key lacks required permission, or ticket is closed (GET endpoint) |
| 404 | Ticket not found |
| 415 | Unsupported content type |
| 500 | Internal server error |

---

## Prerequisites

1. Run the migration SQL script (`setup/scripts/migrate-api-update-v1.sql`) on existing installations
2. Associate API keys with staff members (via the **Mapped Staff** dropdown in `Admin Panel → Manage → API Keys → Edit`)
3. Enable **Can Update Tickets** on API keys that need update/reply/note/read access
