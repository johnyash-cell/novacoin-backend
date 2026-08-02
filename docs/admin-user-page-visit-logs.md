# Admin page visits API

Status: implemented (matches Admin Page Visits UI)  
Audience: frontend / QA  
Last updated: 2026-08-02

---

## Summary

Powers the admin **Page Visits** screen: summary metric cards + aggregated recent-visits table.

| Screen area | Endpoint |
|-------------|----------|
| Summary cards (Total, Unique, Today, This week) | `GET {{baseUrl}}admin/user-page-visit-logs/overview` or `meta.summary` on list |
| Recent visits table | `GET {{baseUrl}}admin/user-page-visit-logs` |
| User profile timeline (raw events) | `GET {{baseUrl}}admin/users/{id}/page-visit-logs` |
| Record a visit (app) | `POST {{baseUrl}}activity/page-visits` |

Auth: admin JWT for read endpoints. Page recording accepts **authenticated or anonymous** visitors.

---

## Recording visits (frontend app)

Call on route change — including public routes like `/login`.

```
POST {{baseUrl}}activity/page-visits
```

| Field | Required | Description |
|-------|----------|-------------|
| `page_path` | yes | Must start with `/` (e.g. `/dashboard`) |
| `page_title` | no | Display label (e.g. `Dashboard`) |
| `referrer` | no | Previous path or referrer URL |
| `traffic_source` | no | `direct`, `app`, `referral`, `organic`, `email` — inferred from referrer when omitted |

Optional `Authorization: Bearer {userToken}`. Without token → logged as **Anonymous** (`user_id` null).

Server captures: `visited_at`, `ip_address`, `user_agent`, `device_type` (`desktop` \| `mobile` \| `tablet`).

```bash
curl -X POST "{{baseUrl}}activity/page-visits" \
  -H "Content-Type: application/json" \
  -d '{"page_path":"/login","page_title":"Sign in"}'
```

---

## Admin list (Page Visits screen)

```
GET {{baseUrl}}admin/user-page-visit-logs
```

### Query parameters

| Param | Default | Description |
|-------|---------|-------------|
| `page` | `1` | Page number |
| `per_page` | `10` | Page size (max `100`) |
| `sort_by` | `newest` | Order by last seen (`newest` = desc) |
| `search` | — | Filter by path or page label (UI path search box) |
| `user_id` | — | Limit to one member |
| `page_path` | — | Exact path match |
| `start_date` / `end_date` | — | Date range on `visited_at` |

### Response — matches UI table rows

Each row is **aggregated** by `path` + visitor (member or anonymous):

```json
{
  "status": true,
  "message": "Page visits fetched successfully",
  "data": [
    {
      "id": "page-visit-a1b2c3...",
      "path": "/dashboard",
      "page_label": "Dashboard",
      "visitor_display_name": "Salma Ibrahim",
      "visitor_username": "salma",
      "member_id": 2,
      "visit_count": 48,
      "last_seen_at": "2026-08-02T10:42:00.000000Z",
      "device": "desktop",
      "source_label": "Direct"
    },
    {
      "id": "page-visit-d4e5f6...",
      "path": "/login",
      "page_label": "Sign in",
      "visitor_display_name": null,
      "visitor_username": null,
      "member_id": null,
      "visit_count": 94,
      "last_seen_at": "2026-08-02T09:12:00.000000Z",
      "device": "mobile",
      "source_label": "Organic"
    }
  ],
  "meta": {
    "summary": {
      "total_visits": 12480,
      "unique_visitors": 3214,
      "today_visits": 186,
      "this_week_visits": 1042
    },
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 12,
      "last_page": 2
    },
    "filters": {
      "search": null,
      "user_id": null,
      "page_path": null,
      "start_date": null,
      "end_date": null
    }
  },
  "errors": null
}
```

### Field mapping → UI

| API field | UI column |
|-----------|-----------|
| `page_label` + `path` | Page |
| `visitor_display_name` / null | Visitor name or **Anonymous** |
| `visitor_username` + `member_id` | `@username` link → `/admin/users/{member_id}` |
| `visit_count` | Visits |
| `last_seen_at` | Last seen (format client-side) |
| `device` | Device badge (`Desktop`, `Mobile`, `Tablet`) |
| `source_label` | Source |

`visitor_username` is derived from the email local-part until a dedicated username column exists.

---

## Summary metrics only

```
GET {{baseUrl}}admin/user-page-visit-logs/overview
```

```json
{
  "status": true,
  "message": "Page visit summary metrics fetched successfully",
  "data": {
    "total_visits": 12480,
    "unique_visitors": 3214,
    "today_visits": 186,
    "this_week_visits": 1042
  },
  "errors": null
}
```

Format numbers client-side (e.g. `12,480`).

---

## User profile (raw event log)

```
GET {{baseUrl}}admin/users/{userId}/page-visit-logs
```

Returns individual visit events (not aggregated) for a member detail screen.

---

## Filter options

```
GET {{baseUrl}}admin/user-page-visit-logs/filter-options
```

---

## cURL examples

```bash
# Page Visits screen — list + summary
curl -X GET "{{baseUrl}}admin/user-page-visit-logs?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {adminToken}"

# Path search (UI filter box)
curl -X GET "{{baseUrl}}admin/user-page-visit-logs?search=wallet" \
  -H "Authorization: Bearer {adminToken}"

# Summary cards only
curl -X GET "{{baseUrl}}admin/user-page-visit-logs/overview" \
  -H "Authorization: Bearer {adminToken}"
```

---

## Database

- `user_page_visit_logs` — raw events  
- Migrations: `2026_08_02_102840_create_user_page_visit_logs_table.php`, `2026_08_02_103138_add_analytics_columns_to_user_page_visit_logs_table.php`

Run migrate yourself when ready — agents do not run migrations.
