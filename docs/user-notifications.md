# User in-app notifications API

Status: implemented  
Audience: frontend / QA  
Last updated: 2026-08-03

---

## Summary

Powers the member **Notifications** panel (bell → sheet).

| Panel need | Endpoint |
|------------|----------|
| List inbox | `GET {{baseUrl}}notifications` |
| Unread badge | `GET {{baseUrl}}notifications/unread-count` or `meta.unread_count` on list |
| Open one | `GET {{baseUrl}}notifications/{id}` |
| Mark one read (tap row) | `POST {{baseUrl}}notifications/{id}/read` |
| Mark all read | `POST {{baseUrl}}notifications/mark-all-as-read` |

Auth: user JWT (`auth:api`). Rows come from `admin_notification_recipients` (created when an admin sends).

---

## List

```
GET {{baseUrl}}notifications
```

| Param | Default | Description |
|-------|---------|-------------|
| `page` | `1` | Page number |
| `per_page` | `20` | Max `100` |
| `sort_by` | `newest` | `newest` \| `oldest` (by broadcast `sent_at`) |
| `unread_only` | — | Boolean; only unread when true |

### Item shape → panel

| API | Panel (`AppNotificationItem`) |
|-----|-------------------------------|
| `id` | `id` (stringified client-side) |
| `title` | `title` |
| `body` | `body` |
| `sent_at` | format → `timeLabel` |
| `is_unread` | `isUnread` |

```json
{
  "status": true,
  "message": "Notifications fetched successfully",
  "data": [
    {
      "id": 12,
      "title": "Welcome to NovaCoin",
      "body": "Your account is ready. Fund your wallet to start investing.",
      "message": "Your account is ready. Fund your wallet to start investing.",
      "sent_at": "2026-08-03T09:00:00.000000Z",
      "is_unread": true,
      "read_at": null,
      "created_at": "2026-08-03T09:00:00.000000Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1,
      "last_page": 1
    },
    "unread_count": 1,
    "filters": {
      "unread_only": false
    }
  },
  "errors": null
}
```

```bash
curl -X GET "{{baseUrl}}notifications?page=1&per_page=20&sort_by=newest" \
  -H "Authorization: Bearer {userToken}"
```

---

## Unread count (bell)

```
GET {{baseUrl}}notifications/unread-count
```

```json
{
  "status": true,
  "message": "Unread notification count fetched successfully",
  "data": { "unread_count": 3 },
  "errors": null
}
```

---

## Show one

```
GET {{baseUrl}}notifications/{id}
```

`{id}` = **recipient** id (not admin broadcast id). Other users’ ids → **404**.

---

## Mark as read

```bash
# One
curl -X POST "{{baseUrl}}notifications/12/read" \
  -H "Authorization: Bearer {userToken}"

# All
curl -X POST "{{baseUrl}}notifications/mark-all-as-read" \
  -H "Authorization: Bearer {userToken}"
```

Idempotent for already-read rows.

---

## Filter options

```
GET {{baseUrl}}notifications/filter-options
```

---

## Security

- Scoped to authenticated `user_id` only (no IDOR)
- Admin send endpoints remain under `/admin/notifications`
