# Admin user account restriction logs API (ban / suspend history)

Status: **implemented**  
Audience: **frontend / QA**  
Last updated: 2026-08-09

---

## Summary

Every ban, suspend, unsuspend, reactivate, and auto suspension-expiry is appended to `user_account_restriction_logs`. Latest status still lives on `users`; this table is the **history**.

| Screen area | Endpoint | Auth |
|-------------|----------|------|
| Restriction history on user profile | `GET /admin/users/{id}/account-restriction-logs` | Admin JWT |
| Filter options | `GET /admin/users/{id}/account-restriction-logs/filter-options` | Admin JWT |

`{{baseUrl}}` includes API prefix (e.g. `https://novacoinv2-backend.test/api/`).

---

## What gets logged

| Action value | When |
|--------------|------|
| `ban` | Admin bans member |
| `suspend` | Admin suspends member (stores `suspended_until`) |
| `unsuspend` | Admin clears suspension early |
| `reactivate` | Admin restores a banned member |
| `suspension_expired` | Timed suspension ends automatically (e.g. on next login) — `performed_by_admin` is `null` |

Each row keeps previous + new status, optional reason, who did it, and timestamp.

---

## List

```
GET {{baseUrl}}admin/users/{id}/account-restriction-logs?page=1&per_page=10&sort_by=newest&action=&start_date=&end_date=
```

| Query | Notes |
|-------|-------|
| `page` / `per_page` | Pagination (max 100) |
| `sort_by` | `newest` \| `oldest` — default `newest` |
| `action` | Optional single action value |
| `start_date` / `end_date` | Optional together, `Y-m-d` |

```bash
curl -X GET "{{baseUrl}}admin/users/1/account-restriction-logs?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {{adminToken}}"
```

**Row shape:**

| Field | Notes |
|-------|-------|
| `action` / `action_label` | e.g. `ban` / `Banned` |
| `previous_account_status` / `*_label` | Before change |
| `new_account_status` / `*_label` | After change |
| `reason` | Optional |
| `suspended_until` | Set on suspend rows |
| `performed_by_admin` | `{ id, first_name, last_name, email }` or `null` (auto expiry) |
| `created_at` | When logged |

---

## Filter options

```
GET {{baseUrl}}admin/users/{id}/account-restriction-logs/filter-options
```

Structured filters only (`action` single-select from this user’s distinct log actions, `date_range`). **No** free-text search.

```bash
curl -X GET "{{baseUrl}}admin/users/1/account-restriction-logs/filter-options" \
  -H "Authorization: Bearer {{adminToken}}"
```
