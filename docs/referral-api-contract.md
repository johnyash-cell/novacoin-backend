# Referral program API (invite code → signup → reward on approved deposit)

Status: **implemented**  
Audience: **frontend / QA** (share this file for wiring)  
Last updated: 2026-08-09

**Related:** deposit approve credits the depositor in [user-wallet-funding-api-contract.md](./user-wallet-funding-api-contract.md). Referral reward runs in the same approve transaction when eligible.

---

## Summary

Members get a unique shareable `referral_code`. New signups (email or Google) may pass that code once. When a referred member’s wallet deposit is **admin-approved**, the referrer may receive a **fixed USD** credit to their wallet (amount + payout mode from admin settings).

| Screen area | Endpoint | Auth |
|-------------|----------|------|
| Register with code | `POST /auth/register` | Public |
| Google signup with code | `POST /auth/google` | Public |
| My referral summary | `GET /referral` | User JWT |
| People I referred | `GET /referral/referred-users` | User JWT |
| Admin program settings | `GET\|PUT /admin/referral-settings` | Admin JWT |
| Admin payout audit filters | `GET /admin/referral-reward-payouts/filter-options` | Admin JWT |
| Admin payout audit list | `GET /admin/referral-reward-payouts` | Admin JWT |

`{{baseUrl}}` includes the API prefix (e.g. `https://novacoinv2-backend.test/api/`).

---

## Envelope & list conventions

Same NovaCoin envelope (`status`, `message`, `data`, `errors`, optional `meta.pagination` / `meta.filters`).

| Rule | Detail |
|------|--------|
| Field names | `snake_case` |
| Pagination | `?page=1&per_page=10` → `meta.pagination` |
| Sort | `?sort_by=newest\|oldest` — default **newest** (`created_at` desc) |
| Search | Free-text on admin payout list only: `?search=` — **not** in filter-options |
| Currency | **USD (`$`)** only. Amount strings use two decimal places (e.g. `"10.00"`) |
| Auth header | `Authorization: Bearer {{token}}` |

Missing/invalid token → **401**.

---

## Locked product rules

| Topic | Rule |
|-------|------|
| Code | Auto-generated per user (unique). Uppercased/trimmed when accepted |
| Attach timing | Set once at signup. Never overwritten later |
| Email register | Optional `referral_code`. Invalid code → **422** (user not created) |
| Google | Optional `referral_code`. Applied **only when creating a new user**. Existing Google login ignores code |
| Self-referral | Not allowed |
| Reward type | Fixed USD from settings — **not** a % of deposit |
| Reward trigger | Admin **approves** a wallet deposit for a referred user |
| Default payout mode | `first_approved_deposit_only` — one reward per referred user |
| Alt payout mode | `every_approved_deposit` — reward on each approved deposit |
| Idempotency | Same approved deposit never pays twice |
| Ledger | Referrer wallet credited with entry type `referral_credit` |
| Levels | One level only (referrer ← referred). No multi-tier |

---

## Payout modes

| `value` | `label` | Behaviour |
|---------|---------|-----------|
| `first_approved_deposit_only` | First approved deposit only | Pay once when that referred user gets their first approved deposit |
| `every_approved_deposit` | Every approved deposit | Pay on each approved deposit for that referred user |

Defaults when unset: reward **`10.00`** USD, mode **`first_approved_deposit_only`**.

---

## Signup — attach referral code

### Email register

```
POST {{baseUrl}}auth/register
```

| Field | Required | Notes |
|-------|----------|-------|
| `first_name` | yes | |
| `last_name` | yes | |
| `email` | yes | unique |
| `password` | yes | confirmed |
| `password_confirmation` | yes | |
| `phone` | no | |
| `referral_code` | no | Must exist on `users.referral_code` when sent |

```bash
curl -X POST "{{baseUrl}}auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Referred",
    "last_name": "Member",
    "email": "referred@example.com",
    "password": "Password1!",
    "password_confirmation": "Password1!",
    "referral_code": "ABCD1234"
  }'
```

**201** — user payload includes:

| Field | Notes |
|-------|-------|
| `referral_code` | New user’s own shareable code |
| `referred_by_user_id` | Referrer user id when code was valid; else `null` |

**422** — invalid code message: **"This referral code is invalid."**

### Google login / signup

```
POST {{baseUrl}}auth/google
```

| Field | Required | Notes |
|-------|----------|-------|
| `id_token` | yes | Google ID token |
| `referral_code` | no | Validated only when a **new** user is created. Stale codes on returning logins are ignored (no error) |

```bash
curl -X POST "{{baseUrl}}auth/google" \
  -H "Content-Type: application/json" \
  -d '{
    "id_token": "{{googleIdToken}}",
    "referral_code": "ABCD1234"
  }'
```

If attach fails for a **new** Google user (unknown / own code) → **422** on `referral_code`.

---

## Member — referral summary

```
GET {{baseUrl}}referral
```

Auth: **user JWT**.

```bash
curl -X GET "{{baseUrl}}referral" \
  -H "Authorization: Bearer {{userToken}}"
```

**200** example:

```json
{
  "status": true,
  "message": "Referral details fetched successfully",
  "data": {
    "referral_code": "ABCD1234",
    "referred_users_count": 1,
    "total_rewards_earned_usd": "10.00",
    "reward_amount_usd": "10.00",
    "payout_mode": "first_approved_deposit_only",
    "payout_mode_label": "First approved deposit only"
  },
  "errors": null
}
```

| Field | Type | Notes |
|-------|------|-------|
| `referral_code` | string | Share with invitees |
| `referred_users_count` | integer | Users with `referred_by_user_id` = me |
| `total_rewards_earned_usd` | string | Sum of my payout rows |
| `reward_amount_usd` | string | Current program reward (next payout uses this) |
| `payout_mode` | string | Current mode value |
| `payout_mode_label` | string | Human label for UI |

Unauthenticated → **401**.

---

## Member — referred users list

```
GET {{baseUrl}}referral/referred-users?page=1&per_page=10&sort_by=newest
```

Auth: **user JWT**.

| Query | Required | Notes |
|-------|----------|-------|
| `page` | no | default 1 |
| `per_page` | no | default 10, max 100 |
| `sort_by` | no | `newest` \| `oldest` — default `newest` |

No `search` and no `/filter-options` companion (sort + pagination only).

```bash
curl -X GET "{{baseUrl}}referral/referred-users?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {{userToken}}"
```

**200** example:

```json
{
  "status": true,
  "message": "Referred users fetched successfully",
  "data": [
    {
      "id": 42,
      "first_name": "Referred",
      "last_name": "Member",
      "email": "referred@example.com",
      "created_at": "2026-08-09T12:00:00.000000Z"
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
      "sort_by": "newest"
    }
  },
  "errors": null
}
```

---

## Admin — referral settings

### Show

```
GET {{baseUrl}}admin/referral-settings
```

Auth: **admin JWT**.

```bash
curl -X GET "{{baseUrl}}admin/referral-settings" \
  -H "Authorization: Bearer {{adminToken}}"
```

**200** example:

```json
{
  "status": true,
  "message": "Referral settings fetched successfully",
  "data": {
    "reward_amount_usd": "10.00",
    "payout_mode": "first_approved_deposit_only",
    "payout_mode_label": "First approved deposit only",
    "allowed_payout_modes": [
      {
        "value": "first_approved_deposit_only",
        "label": "First approved deposit only"
      },
      {
        "value": "every_approved_deposit",
        "label": "Every approved deposit"
      }
    ]
  },
  "errors": null
}
```

Use `allowed_payout_modes` to populate the admin select — do not hardcode mode options in the client.

### Update

```
PUT {{baseUrl}}admin/referral-settings
```

| Field | Required | Notes |
|-------|----------|-------|
| `reward_amount_usd` | at least one of the two | numeric, `> 0` |
| `payout_mode` | at least one of the two | must be a known mode value |

```bash
curl -X PUT "{{baseUrl}}admin/referral-settings" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "reward_amount_usd": 15.5,
    "payout_mode": "every_approved_deposit"
  }'
```

**200** — same shape as show (updated values).  
Empty body → **422** (`Provide at least one of reward_amount_usd or payout_mode.`).  
Unknown `payout_mode` → **422**.

---

## Admin — referral reward payouts

### Filter options

```
GET {{baseUrl}}admin/referral-reward-payouts/filter-options
```

```bash
curl -X GET "{{baseUrl}}admin/referral-reward-payouts/filter-options" \
  -H "Authorization: Bearer {{adminToken}}"
```

**200** — structured filters only (no search text field):

```json
{
  "status": true,
  "message": "Filter options retrieved successfully",
  "data": {
    "filters": [
      {
        "key": "date_range",
        "label": "Date Range",
        "description": "Filter by payout date (start_date & end_date)",
        "type": "date-range",
        "options": null
      }
    ],
    "total_available_filters": 1
  },
  "errors": null
}
```

### List

```
GET {{baseUrl}}admin/referral-reward-payouts?page=1&per_page=10&sort_by=newest&search=&start_date=&end_date=&referrer_user_id=&referred_user_id=
```

| Query | Required | Notes |
|-------|----------|-------|
| `page` | no | default 1 |
| `per_page` | no | default 10, max 100 |
| `sort_by` | no | `newest` \| `oldest` |
| `search` | no | Matches referrer/referred email, name, or referral_code |
| `start_date` / `end_date` | together | `Y-m-d`; both required if either sent; filters payout `created_at` |
| `referrer_user_id` | no | integer user id |
| `referred_user_id` | no | integer user id |

```bash
curl -X GET "{{baseUrl}}admin/referral-reward-payouts?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {{adminToken}}"
```

**200** row shape:

| Field | Type | Notes |
|-------|------|-------|
| `id` | integer | Payout id |
| `amount` | string | USD, two decimals |
| `referrer_user` | object | `id`, `first_name`, `last_name`, `email`, `referral_code` |
| `referred_user` | object | same shape |
| `wallet_deposit_id` | integer | Deposit that triggered the reward |
| `created_at` | ISO-8601 | Payout time |

`meta.filters` echoes applied `search`, `sort_by`, `start_date`, `end_date`, `referrer_user_id`, `referred_user_id`.

---

## User object fields (auth / profile)

Member `UserResource` (login, register, me, etc.) already exposes:

| Field | Notes |
|-------|-------|
| `referral_code` | Always present for members |
| `referred_by_user_id` | Referrer id or `null` |

Admin user payloads also include these fields where the admin user resource is used.

---

## Frontend wiring checklist

1. **Invite screen** — `GET /referral` for code + counts + current reward copy.
2. **Referred list** — `GET /referral/referred-users` with pagination.
3. **Signup** — optional `referral_code` on register / Google (from invite link query param is a FE concern).
4. **Admin settings** — load `allowed_payout_modes` from `GET /admin/referral-settings`; save via `PUT`.
5. **Admin audit** — filter-options + list; use `search` on the list endpoint only.
6. **Do not invent** % rewards, multi-level trees, or invest-based triggers — not in this API.
)
