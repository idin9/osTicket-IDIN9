# osTicket API Usage (v1.18.4.01)

## Authentication

All API requests require an `X-API-Key` header with a valid API key registered in the system.

## API Key Configuration

API keys must have the following permissions enabled:
- `can_create_tickets` — Create new tickets
- `can_update_tickets` — Update existing tickets and post replies
- `can_exec_cron` — Execute cron tasks

For ticket updates and replies, the API key **must be associated with a staff member** (set `staff_id` on the key record).

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
POST /api/tickets/<id>.(xml|json)
```

Updates ticket metadata (topic, SLA, due date, source, assigned user).

**Request body (JSON):**
```json
{
  "topicId": 2,
  "slaId": 1,
  "duedate": "2026-12-31",
  "source": "Phone",
  "user_id": 5,
  "note": "Updated via API"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `topicId` | int | Yes | Help topic ID |
| `slaId` | int | No | SLA ID |
| `duedate` | string | No | Due date (YYYY-MM-DD) |
| `source` | string | No | Ticket source |
| `user_id` | int | No | Assign ticket to user |
| `note` | string | No | Internal note |

**Response:** `200 OK` — Returns ticket number
```
200
201234
```

---

### 3. Post Reply to Ticket

```
POST /api/tickets/<id>/reply.(xml|json)
```

Posts an agent response to an existing ticket.

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

## Error Responses

| Code | Meaning |
|---|---|
| 400 | Bad request — invalid or missing data |
| 401 | Unauthorized — missing/invalid API key or insufficient permissions |
| 403 | Forbidden — key lacks required permission |
| 404 | Ticket not found |
| 415 | Unsupported content type |
| 500 | Internal server error |

---

## Prerequisites

1. Run the migration SQL script (`setup/scripts/migrate-api-update-v1.sql`) on existing installations
2. Associate API keys with staff members (`staff_id` column) for update and reply endpoints
3. Enable `can_update_tickets` on API keys that need update/reply access
