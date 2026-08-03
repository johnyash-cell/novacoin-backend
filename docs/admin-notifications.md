# Admin in-app notifications API

Status: implemented (Phase A — website/in-app DB only)  
Audience: frontend / QA  
Last updated: 2026-08-02

---

## Summary

Powers the admin **Notifications** builder + **Sent** history.

| Screen area | Endpoint |
|-------------|----------|
| Send notification | `POST {{baseUrl}}admin/notifications` |
| Sent history (all admins) | `GET {{baseUrl}}admin/notifications` |
| Filter options | `GET {{baseUrl}}admin/notifications/filter-options` |
| Audience picker | Reuse `GET {{baseUrl}}admin/users` (+ `search`) |

**Delivery:** in-website only. Rows are stored in the database. No email / push.

Auth: admin JWT on all endpoints below.

---

## Send

```
POST {{baseUrl}}admin/notifications
```

### Body

| Field | Required | Rules |
|-------|----------|-------|
| `title` | yes | string, max 120 |
| `message` | yes | string, max 500 |
| `audience_mode` | yes | `all_users` \| `selected_users` |
| `user_ids` | when selected | array of user ids, min 1, each must exist |
| `delivery` | yes | `send_now` only |

### Success (`201`)

```json
{
  "status": true,
  "message": "Notification sent successfully",
  "data": {
    "id": 1,
    "title": "Maintenance window tonight",
    "message": "Expect brief downtime after midnight.",
    "audience_mode": "all_users",
    "audience_label": "All users",
    "audience_count": 120,
    "delivery": "send_now",
    "sent_at": "2026-08-02T12:00:00.000000Z",
    "sent_by": {
      "admin_id": 1,
      "name": "Super Admin",
      "email": "superadmin@novacoin.test"
    },
    "created_at": "2026-08-02T12:00:00.000000Z"
  },
  "errors": null
}
```

Side effects:

1. One row in `admin_notifications` (Sent history)
2. One row per recipient in `admin_notification_recipients` (future member inbox)

```bash
# All users
curl -X POST "{{baseUrl}}admin/notifications" \
  -H "Authorization: Bearer {adminToken}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Maintenance window tonight",
    "message": "Expect brief downtime after midnight.",
    "audience_mode": "all_users",
    "delivery": "send_now"
  }'

# Selected users
curl -X POST "{{baseUrl}}admin/notifications" \
  -H "Authorization: Bearer {adminToken}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Wallet update",
    "message": "Your wallet features were updated.",
    "audience_mode": "selected_users",
    "user_ids": [2, 5, 9],
    "delivery": "send_now"
  }'
```

### Common errors (`422`)

- Selected mode without `user_ids` → validation on `user_ids`
- `all_users` but zero members in DB → validation on `audience_mode`
- Unsupported `delivery` (e.g. `schedule`) → validation on `delivery`

---

## Sent history

```
GET {{baseUrl}}admin/notifications
```

Returns notifications sent by **all admins**, default `sort_by=newest` on `sent_at`.

### Query parameters

| Param | Default | Description |
|-------|---------|-------------|
| `page` | `1` | Page number |
| `per_page` | `10` | Page size (max 100) |
| `sort_by` | `newest` | `newest` \| `oldest` |
| `audience_mode` | — | `all_users` \| `selected_users` |
| `start_date` / `end_date` | — | `Y-m-d` on `sent_at` |

### Response shape (Sent rail)

| API field | UI |
|-----------|-----|
| `title` | Notification title |
| `message` | Body |
| `audience_label` + `audience_count` | Audience chip / count |
| `delivery` | `send_now` |
| `sent_at` | When sent (format client-side) |
| `sent_by` | Optional multi-admin attribution |

```bash
curl -X GET "{{baseUrl}}admin/notifications?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {adminToken}"
```

---

## Filter options

```
GET {{baseUrl}}admin/notifications/filter-options
```

Structured filters only (no free-text search). Audience picker search stays on `GET admin/users?search=...`.

---

## Database

- `admin_notifications` — broadcast / Sent history
- `admin_notification_recipients` — per-user in-app rows (`read_at` reserved for later)

Migrations:

- `2026_08_02_121203_create_admin_notifications_table.php`
- `2026_08_02_121204_create_admin_notification_recipients_table.php`

Run migrate yourself when ready — agents do not run migrations.

---

## Member inbox

User-facing list/read/mark-read: see [`docs/user-notifications.md`](user-notifications.md).

## Still out of scope

Schedule, channels (email/push), drafts, edit/resend.
