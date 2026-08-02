# Admin authentication login logs API

Status: implemented  
Audience: frontend / QA  
Last updated: 2026-08-02

---

## Summary

Every sign-in attempt (user app + admin backoffice) is stored in `authentication_login_logs`.

**One list endpoint** covers all cases:

```
GET {{baseUrl}}admin/authentication-login-logs
```

Use query params to scope results — all members, one directory user, one email, admins only, failed attempts, date range, etc.

Auth: **admin JWT** (`Authorization: Bearer {adminToken}`).

---

## What gets logged automatically

| Event | Route | `actor_type` | `login_method` |
|-------|-------|--------------|------------------|
| User password login (success) | `POST /api/auth/login` | `user` | `password` |
| User password login (failure) | `POST /api/auth/login` | `user` | `password` |
| User Google login (success) | `POST /api/auth/google` | `user` | `google` |
| User Google login (failure) | `POST /api/auth/google` | `user` | `google` |
| Admin password login (success) | `POST /api/admin/auth/login` | `admin` | `password` |
| Admin password login (failure) | `POST /api/admin/auth/login` | `admin` | `password` |

Each row stores: `email`, `ip_address`, `user_agent`, `was_successful`, optional `failure_reason`, `created_at`.

---

## Primary endpoint (use this)

### List login logs

```
GET {{baseUrl}}admin/authentication-login-logs
```

#### Query parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | integer | `1` | Page number |
| `per_page` | integer | `10` | Page size (max `100`) |
| `sort_by` | `newest` \| `oldest` | `newest` | Order by `created_at` |
| `user_id` | integer | — | Scope to a **directory user** (see below) |
| `email` | string | — | Scope to exact email (works for admin-only staff) |
| `actor_type` | `user` \| `admin` | — | Filter by app user vs backoffice admin login |
| `login_method` | `password` \| `google` | — | Filter by sign-in method |
| `was_successful` | boolean | — | `1` / `0` — success vs failed |
| `start_date` | `Y-m-d` | — | Date range start (requires `end_date`) |
| `end_date` | `Y-m-d` | — | Date range end (requires `start_date`) |

#### How to fetch logs for “any kind of user”

| Who you’re viewing | Query |
|--------------------|-------|
| **Everyone** (global audit) | No scope params |
| **Directory user** (user profile page) | `?user_id={id}` |
| **Same as above** (shorthand URL) | `GET {{baseUrl}}admin/users/{id}/authentication-login-logs` |
| **Admin-only account** (no `users` row, e.g. super admin) | `?email=superadmin@novacoin.test` |
| **Only app logins** | `?actor_type=user` |
| **Only backoffice logins** | `?actor_type=admin` |
| **Failed attempts only** | `?was_successful=0` |

#### `user_id` scope behaviour

When `user_id` is set, the API returns **all login activity tied to that person**:

- Rows where `email` matches the user’s email (includes **both** `user` and `admin` actor types)
- Rows where `actor_type = user` and `actor_id` matches (covers email changes)

So a promoted user (same email in `users` + `admins`) shows app logins **and** backoffice logins on one profile.

#### Empty results

Empty `data: []` is valid when that person has never signed in. Logs for other emails (e.g. `superadmin@novacoin.test`) do **not** appear on `testuser@example.com`’s profile.

---

### Filter options (companion)

```
GET {{baseUrl}}admin/authentication-login-logs/filter-options
```

Returns structured filters for dropdowns: `actor_type`, `login_method`, `was_successful`, `date_range`.  
Does **not** include free-text search (search is not a filter-option per API standards).

---

## Convenience alias (same payload)

```
GET {{baseUrl}}admin/users/{userId}/authentication-login-logs
```

Identical to `GET .../authentication-login-logs?user_id={userId}`.  
Accepts the same optional filters (`login_method`, `was_successful`, `start_date`, `end_date`, `sort_by`, pagination).

---

## Response envelope

### Success

```json
{
  "status": true,
  "message": "Authentication login logs fetched successfully",
  "data": [
    {
      "id": 1,
      "actor_type": "user",
      "actor_type_label": "User",
      "actor_id": 1,
      "email": "testuser@example.com",
      "ip_address": "127.0.0.1",
      "user_agent": "Mozilla/5.0 ...",
      "login_method": "password",
      "login_method_label": "Password",
      "was_successful": true,
      "failure_reason": null,
      "created_at": "2026-08-02T10:00:00.000000Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 1,
      "last_page": 1
    },
    "filters": {
      "user_id": 1,
      "email": null,
      "actor_type": null,
      "login_method": null,
      "was_successful": null,
      "start_date": null,
      "end_date": null
    }
  },
  "errors": null
}
```

### Log row fields

| Field | Description |
|-------|-------------|
| `actor_type` | `user` (app) or `admin` (backoffice) |
| `actor_type_label` | Human label |
| `actor_id` | ID in `users` or `admins` when known; `null` on some failed attempts |
| `email` | Email used at sign-in |
| `login_method` | `password` or `google` |
| `was_successful` | `true` / `false` |
| `failure_reason` | Set when `was_successful` is false |

---

## cURL examples

```bash
# All login logs (newest first)
curl -X GET "{{baseUrl}}admin/authentication-login-logs?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {adminToken}"

# One directory user (user profile page)
curl -X GET "{{baseUrl}}admin/authentication-login-logs?user_id=1&page=1&per_page=10" \
  -H "Authorization: Bearer {adminToken}"

# Admin-only staff by email (no users table row)
curl -X GET "{{baseUrl}}admin/authentication-login-logs?email=superadmin@novacoin.test" \
  -H "Authorization: Bearer {adminToken}"

# Failed backoffice logins in a date window
curl -X GET "{{baseUrl}}admin/authentication-login-logs?actor_type=admin&was_successful=0&start_date=2026-08-01&end_date=2026-08-31" \
  -H "Authorization: Bearer {adminToken}"

# Filter options for UI dropdowns
curl -X GET "{{baseUrl}}admin/authentication-login-logs/filter-options" \
  -H "Authorization: Bearer {adminToken}"

# Shorthand — same as user_id query above
curl -X GET "{{baseUrl}}admin/users/1/authentication-login-logs?page=1&per_page=10" \
  -H "Authorization: Bearer {adminToken}"
```

---

## Frontend integration notes

1. **User profile login history** — call either:
   - `GET /admin/authentication-login-logs?user_id={id}`, or
   - `GET /admin/users/{id}/authentication-login-logs`

2. **Global audit / security screen** — call without `user_id` / `email`; add filters from filter-options.

3. **Admin staff with no user account** — use `email={adminEmail}`; `user_id` is not available.

4. **`actor_type` in UI** — use `actor_type_label` for display; tolerate unknown future values.

5. **Pagination** — read `meta.pagination`; default sort is `created_at` descending (`sort_by=newest`).

---

## Errors

| HTTP | When |
|------|------|
| `401` | Missing or invalid admin token |
| `404` | `user_id` does not exist (global endpoint with invalid id) |
| `422` | Invalid query params (bad date range, unknown enum, etc.) |

Validation messages use the standard API envelope (`status: false`, human-readable `message`, field `errors`).

---

## Database

Table: `authentication_login_logs` (migration `2026_08_01_195327_create_authentication_login_logs_table.php`).

Run migrate yourself when ready — agents do not run migrations.
