# Admin investment packages API (Phase B — package CRUD)

Status: **implemented (admin CRUD + member catalog / invest / holdings / daily return escrow / maturity payout)**  
Audience: **frontend / QA** (share this file for wiring)  
Last updated: 2026-08-09 (first earning = next calendar day after subscribe)

**Naming:** *Investment plan* (older domain doc) ≡ *investment package* (this contract). API uses **`investment-packages`**.

**This pass:** admin package CRUD **and** member catalog, invest from wallet balance, my holdings, flat daily return escrow + maturity wallet payout.  

**Related:** wallet funding (crypto deposit → approve → USD balance) is in [user-wallet-funding-api-contract.md](./user-wallet-funding-api-contract.md).

---

## Summary

Powers admin `/admin/investment-packages` (list + create sheet + detail edit / status / featured).

| Screen area | Endpoint |
|-------------|----------|
| Package list + summary counts | `GET {{baseUrl}}admin/investment-packages` |
| Filter options | `GET {{baseUrl}}admin/investment-packages/filter-options` |
| Create package | `POST {{baseUrl}}admin/investment-packages` |
| Package detail | `GET {{baseUrl}}admin/investment-packages/{id}` |
| Update package | `PUT {{baseUrl}}admin/investment-packages/{id}` or `PATCH` |
| Delete package | `DELETE {{baseUrl}}admin/investment-packages/{id}` |
| Set availability status | `PATCH {{baseUrl}}admin/investment-packages/{id}/availability-status` |
| Set featured | `PATCH {{baseUrl}}admin/investment-packages/{id}/featured` |

### Auth

All routes below need **admin JWT**:

```
Authorization: Bearer {{adminToken}}
```

Get token via existing login:

```bash
curl -X POST "{{baseUrl}}admin/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your-password"}'
```

Missing/invalid token → **401**. Wrong guard / insufficient access → **403**.

`{{baseUrl}}` includes the API prefix (e.g. `https://novacoinv2-backend.test/api/`).

---

## Envelope & list conventions

Same NovaCoin envelope as other admin APIs:

```json
{
  "status": true,
  "message": "Human-readable outcome",
  "data": {},
  "errors": null,
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 100,
      "last_page": 10
    },
    "filters": {},
    "summary": {}
  }
}
```

| Rule | Detail |
|------|--------|
| Field names | `snake_case` |
| Pagination | `?page=1&per_page=10` → `meta.pagination` |
| Sort | `?sort_by=newest\|oldest` — default **newest** (`created_at` desc) |
| Search | Free-text on list only: `?search=` — **not** in filter-options |
| Currency | **USD (`$`)** only. Amounts are numerics; FE formats labels |
| Unknown enums | Never 500 — return raw string + optional `*_label` |

---

## Package model

| API field | Type | Required on create | Notes |
|-----------|------|--------------------|-------|
| `id` | integer | — | Bigint auto-increment |
| `name` | string | yes | Display name |
| `short_pitch` | string | yes | Card subtitle; max 160 |
| `description` | string | yes | Longer about copy |
| `expected_return_percent` | number | yes | `> 0`, max 500 |
| `term_days` | integer | yes | `> 0` |
| `minimum_amount_usd` | number | yes | `> 0` — invest floor later |
| `maximum_amount_usd` | number \| null | no | Optional; when set must be `≥ minimum_amount_usd` |
| `max_participants` | integer | yes | `≥ 1` |
| `joined_count` | integer | yes | Admin-settable current participants; `0 ≤ joined_count ≤ max_participants` |
| `risk_level` | string | yes | `conservative` \| `balanced` \| `growth` |
| `availability_status` | string | yes | Stored intent: `open` \| `limited` \| `full` \| `expired` |
| `expires_at` | ISO-8601 \| null | no | Optional schedule; when due → auto-expire (see Expiry) |
| `is_featured` | boolean | no | Default `false` |
| `highlights` | string[] | no | Optional; DB may store `null`. API always returns an array (`[]` when null/omitted); one bullet per entry |
| `created_at` | ISO-8601 | — | |
| `updated_at` | ISO-8601 | — | |

### Derived (read-only on responses)

| Field | Rule |
|-------|------|
| `remaining_seats` | `max(0, max_participants - joined_count)` |
| `effective_availability_status` | If stored status is `expired` → `expired`. Else if `joined_count >= max_participants` → `full`. Else → stored `availability_status`. |
| `risk_level_label` | e.g. `Conservative` (humanized fallback for unknown) |
| `availability_status_label` | e.g. `Open` |
| `effective_availability_status_label` | Label for effective status |

Member-facing capacity copy (later catalog): **`{joined_count} of {max_participants}`** (e.g. `100 of 101`). API returns the two integers; FE formats.

### Availability & capacity rules

1. Admin may set `availability_status` to `open`, `limited`, `full`, or `expired`.
2. If `joined_count >= max_participants`, admin **cannot** set status to `open` or `limited` → **422** with a human message (e.g. package is at capacity).
3. Setting status to `full` or `expired` is always allowed (subject to validation).
4. `max_participants` on update cannot go below current `joined_count` → **422**.

### Expiry

1. Admin may set `availability_status` to `expired` at any time.
2. Admin may set or clear optional `expires_at`.
3. When `expires_at` is in the past (or becomes due), the package **auto-expires**: stored `availability_status` becomes `expired`.
   - **Impl (locked):** scheduled Artisan command (primary) **plus** persist-on-read safety (if a due package is loaded and still not `expired`, flip and save before responding). Must be durable in DB, not a virtual-only flag.
4. Expired packages block invest when that flow exists (**422**).

### Sample package object

```json
{
  "id": 1,
  "name": "Growth 90",
  "short_pitch": "Balanced mid-term package for stronger compounding.",
  "description": "A ninety-day package designed for members ready to leave capital at work longer.",
  "expected_return_percent": 22,
  "term_days": 90,
  "minimum_amount_usd": 500,
  "maximum_amount_usd": 10000,
  "max_participants": 101,
  "joined_count": 100,
  "risk_level": "balanced",
  "risk_level_label": "Balanced",
  "availability_status": "open",
  "availability_status_label": "Open",
  "effective_availability_status": "open",
  "effective_availability_status_label": "Open",
  "expires_at": null,
  "is_featured": true,
  "highlights": [
    "Most popular mid-term plan",
    "Higher return than Steady 30",
    "Clear 90-day maturity"
  ],
  "remaining_seats": 1,
  "created_at": "2026-05-01T10:00:00.000000Z",
  "updated_at": "2026-07-15T14:22:00.000000Z"
}
```

---

## List packages

```
GET {{baseUrl}}admin/investment-packages
```

### Query

| Param | Required | Rules |
|-------|----------|-------|
| `page` | no | integer ≥ 1; default 1 |
| `per_page` | no | 1–100; default 10 |
| `sort_by` | no | `newest` \| `oldest`; default `newest` |
| `search` | no | string; matches `name`, `short_pitch` |
| `availability_status` | no | `open` \| `limited` \| `full` \| `expired` |
| `risk_level` | no | `conservative` \| `balanced` \| `growth` |
| `is_featured` | no | boolean |

### Success (`200`)

`data`: array of package objects (full shape OK for Phase B).

`meta.summary` — **global** counts (ignore search/filters) by **stored** `availability_status` (not capacity-adjusted). Per-row capacity still uses `effective_availability_status`.

| Key | Meaning |
|-----|---------|
| `open` | Stored `availability_status = open` |
| `limited` | Stored `limited` |
| `full` | Stored `full` only |
| `expired` | Stored `expired` |
| `total` | All packages |

`meta.filters` echoes applied query filters.  
`meta.pagination` as usual.

### Example success (`200`)

```json
{
  "status": true,
  "message": "Investment packages fetched successfully",
  "data": [
    {
      "id": 1,
      "name": "Growth 90",
      "short_pitch": "Balanced mid-term package for stronger compounding.",
      "description": "A ninety-day package designed for members ready to leave capital at work longer.",
      "expected_return_percent": 22,
      "term_days": 90,
      "minimum_amount_usd": 500,
      "maximum_amount_usd": 10000,
      "max_participants": 101,
      "joined_count": 100,
      "risk_level": "balanced",
      "risk_level_label": "Balanced",
      "availability_status": "open",
      "availability_status_label": "Open",
      "effective_availability_status": "open",
      "effective_availability_status_label": "Open",
      "expires_at": null,
      "is_featured": true,
      "highlights": [
        "Most popular mid-term plan",
        "Higher return than Steady 30",
        "Clear 90-day maturity"
      ],
      "remaining_seats": 1,
      "created_at": "2026-05-01T10:00:00.000000Z",
      "updated_at": "2026-07-15T14:22:00.000000Z"
    }
  ],
  "errors": null,
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 1,
      "last_page": 1
    },
    "filters": {
      "search": "Growth",
      "availability_status": null,
      "risk_level": null,
      "is_featured": null,
      "sort_by": "newest"
    },
    "summary": {
      "open": 2,
      "limited": 1,
      "full": 1,
      "expired": 0,
      "total": 4
    }
  }
}
```

Capacity UI copy: format **`{joined_count} of {max_participants}`** (e.g. `100 of 101`) on the client.

```bash
curl -X GET "{{baseUrl}}admin/investment-packages?page=1&per_page=10&sort_by=newest&search=Growth" \
  -H "Authorization: Bearer {{adminToken}}"
```

---

## Filter options

```
GET {{baseUrl}}admin/investment-packages/filter-options
```

Structured filters only — **no** search / `type: "text"`.

### Success (`200`)

```json
{
  "status": true,
  "message": "Filter options retrieved successfully",
  "data": {
    "filters": [
      {
        "key": "availability_status",
        "label": "Availability",
        "description": "Filter by availability status",
        "type": "single-select",
        "options": [
          { "value": "open", "label": "Open" },
          { "value": "limited", "label": "Limited" },
          { "value": "full", "label": "Full" },
          { "value": "expired", "label": "Expired" }
        ]
      },
      {
        "key": "risk_level",
        "label": "Risk",
        "description": "Filter by risk level",
        "type": "single-select",
        "options": [
          { "value": "conservative", "label": "Conservative" },
          { "value": "balanced", "label": "Balanced" },
          { "value": "growth", "label": "Growth" }
        ]
      },
      {
        "key": "is_featured",
        "label": "Featured",
        "description": "Filter by featured flag",
        "type": "single-select",
        "options": [
          { "value": "true", "label": "Featured" },
          { "value": "false", "label": "Not featured" }
        ]
      }
    ],
    "total_available_filters": 3
  },
  "errors": null
}
```

Options for `availability_status` / `risk_level` should prefer **distinct values present in DB** plus known labels; if the table is empty, return the known catalog above so the admin UI still works.

```bash
curl -X GET "{{baseUrl}}admin/investment-packages/filter-options" \
  -H "Authorization: Bearer {{adminToken}}"
```

---

## Create package

```
POST {{baseUrl}}admin/investment-packages
```

### Body

| Field | Required | Rules |
|-------|----------|-------|
| `name` | yes | string, max 120 |
| `short_pitch` | yes | string, max 160 |
| `description` | yes | string, max 5000 |
| `expected_return_percent` | yes | number, `> 0`, max 500 |
| `term_days` | yes | integer, `> 0` |
| `minimum_amount_usd` | yes | number, `> 0` |
| `maximum_amount_usd` | no | number, `≥ minimum_amount_usd` when present |
| `max_participants` | yes | integer, `≥ 1` |
| `joined_count` | yes | integer, `≥ 0`, `≤ max_participants` |
| `risk_level` | yes | `conservative` \| `balanced` \| `growth` |
| `availability_status` | yes | `open` \| `limited` \| `full` \| `expired` |
| `expires_at` | no | ISO-8601 datetime or null; if set, should be in the future on create (422 otherwise) |
| `is_featured` | no | boolean; default `false` |
| `highlights` | no | array of strings; each max 200; default `[]` |

Capacity vs status: same rules as model (cannot create as `open`/`limited` when already at capacity).

### Success (`201`)

Message: `"Investment package created successfully"`  
`data`: full package object.

```bash
curl -X POST "{{baseUrl}}admin/investment-packages" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Growth 90",
    "short_pitch": "Balanced mid-term package for stronger compounding.",
    "description": "A ninety-day package designed for members ready to leave capital at work longer.",
    "expected_return_percent": 22,
    "term_days": 90,
    "minimum_amount_usd": 500,
    "maximum_amount_usd": 10000,
    "max_participants": 101,
    "joined_count": 100,
    "risk_level": "balanced",
    "availability_status": "open",
    "expires_at": null,
    "is_featured": true,
    "highlights": [
      "Most popular mid-term plan",
      "Higher return than Steady 30",
      "Clear 90-day maturity"
    ]
  }'
```

---

## Show package

```
GET {{baseUrl}}admin/investment-packages/{id}
```

### Success (`200`)

Message: `"Investment package fetched successfully"`  
`data`: full package object.

### Errors

| Case | HTTP | Message (example) |
|------|------|-------------------|
| Unknown id | 404 | `"Investment package not found"` |

```bash
curl -X GET "{{baseUrl}}admin/investment-packages/1" \
  -H "Authorization: Bearer {{adminToken}}"
```

---

## Update package

```
PUT {{baseUrl}}admin/investment-packages/{id}
PATCH {{baseUrl}}admin/investment-packages/{id}
```

Same fields as create (all required on `PUT`; partial on `PATCH`). Re-validate capacity / status / min-max / `max_participants ≥ joined_count`.

### Success (`200`)

Message: `"Investment package updated successfully"`  
`data`: full package object.

### Errors

| Case | HTTP | Message (example) |
|------|------|-------------------|
| Unknown id | 404 | `"Investment package not found"` |
| Validation / capacity | 422 | Human-readable; `errors` keyed by field |

```bash
curl -X PUT "{{baseUrl}}admin/investment-packages/1" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Growth 90",
    "short_pitch": "Balanced mid-term package for stronger compounding.",
    "description": "Updated description.",
    "expected_return_percent": 22,
    "term_days": 90,
    "minimum_amount_usd": 500,
    "maximum_amount_usd": 10000,
    "max_participants": 101,
    "joined_count": 100,
    "risk_level": "balanced",
    "availability_status": "limited",
    "expires_at": "2026-12-31T23:59:59.000000Z",
    "is_featured": true,
    "highlights": ["Clear 90-day maturity"]
  }'
```

---

## Delete package

```
DELETE {{baseUrl}}admin/investment-packages/{id}
```

Hard delete when the package has **no** member holdings (holdings come in a later pass — until then deletes always succeed if the row exists).

When holdings exist later: **422** — `"This investment package cannot be deleted because members have invested in it"`.

### Success (`200`)

Message: `"Investment package deleted successfully"`  
`data`: `null` (same as admin user delete).

### Errors

| Case | HTTP | Message (example) |
|------|------|-------------------|
| Unknown id | 404 | `"Investment package not found"` |
| Has holdings (later) | 422 | Cannot delete while members invested |

```bash
curl -X DELETE "{{baseUrl}}admin/investment-packages/1" \
  -H "Authorization: Bearer {{adminToken}}"
```

---

## Set availability status

```
PATCH {{baseUrl}}admin/investment-packages/{id}/availability-status
```

### Body

| Field | Required | Rules |
|-------|----------|-------|
| `availability_status` | yes | `open` \| `limited` \| `full` \| `expired` |

Same capacity gate: cannot set `open` / `limited` when at capacity → **422**.

### Success (`200`)

Message: `"Investment package availability updated successfully"`  
`data`: full package object.

```bash
curl -X PATCH "{{baseUrl}}admin/investment-packages/1/availability-status" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{"availability_status": "expired"}'
```

---

## Set featured

```
PATCH {{baseUrl}}admin/investment-packages/{id}/featured
```

### Body

| Field | Required | Rules |
|-------|----------|-------|
| `is_featured` | yes | boolean |

### Success (`200`)

Message: `"Investment package featured flag updated successfully"`  
`data`: full package object.

```bash
curl -X PATCH "{{baseUrl}}admin/investment-packages/1/featured" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{"is_featured": true}'
```

---

## Field alias map (old domain doc → this API)

| Older `InvestmentPlan` name | This contract |
|-----------------------------|-----------------|
| `return_percentage` | `expected_return_percent` |
| `duration_days` | `term_days` |
| `minimum_investment_amount` | `minimum_amount_usd` |
| `maximum_investment_amount` | `maximum_amount_usd` |
| `is_active` | replaced by `availability_status` (+ `expired`) |
| `currency_code` | omitted — USD only |

---

## Member invest (implemented)

Powers member Invest screen: **Packages** tab + **My investments** tab.

| Screen area | Endpoint |
|-------------|----------|
| Package catalog | `GET {{baseUrl}}investment-packages` |
| Package detail / confirm sheet | `GET {{baseUrl}}investment-packages/{id}` |
| Place investment | `POST {{baseUrl}}investment-packages/{id}/invest` |
| My holdings list | `GET {{baseUrl}}investments` |
| Single holding | `GET {{baseUrl}}investments/{id}` |
| Daily earnings log | `GET {{baseUrl}}investments/{id}/daily-earnings` |

All member routes require **`Authorization: Bearer {user_jwt}`**.

Expired / full packages stay in the catalog (`can_invest: false`); FE disables the invest action.

### GET {{baseUrl}}investment-packages

Query: `page`, `per_page`, `sort_by=newest|oldest`, `search`.

Featured packages sort first, then `created_at`.

**`data[]` (member package card):** same core fields as admin list, plus:

| Field | Notes |
|-------|-------|
| `can_invest` | `true` when package is joinable (open/limited, seats left, not expired) |
| `effective_availability_status` | Use for disabled styling |
| `remaining_seats` | `max_participants - joined_count` |

**`meta.summary`:** `total`, `joinable`, `expired`.

```bash
curl -X GET "{{baseUrl}}investment-packages?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {{userToken}}"
```

### GET {{baseUrl}}investment-packages/{id}

Returns one member package object (same shape as catalog item, includes `description` + `highlights`).

```bash
curl -X GET "{{baseUrl}}investment-packages/1" \
  -H "Authorization: Bearer {{userToken}}"
```

### POST {{baseUrl}}investment-packages/{id}/invest

Body:

```json
{
  "amount_usd": 1000
}
```

Rules:

- Debits member **USD wallet balance** atomically.
- Creates holding with **`status: active`**.
- Increments package **`joined_count`** by 1.
- Validates min/max amount, capacity, expiry, and sufficient balance.

**201** — `data` is an investment object:

| Field | Notes |
|-------|-------|
| `amount_usd` | Invested principal |
| `expected_return_amount_usd` | Snapshot at invest time |
| `expected_payout_amount_usd` | Principal + expected return |
| `accrued_return_usd` | Return escrow so far (string 2dp); starts at `"0.00"` |
| `today_earning_usd` | Today’s daily log amount (string 2dp) |
| `total_earned_return_usd` | Same as `accrued_return_usd` |
| `payout_completed_at` | Set when principal + return credited to spendable wallet |
| `effective_status` | `active` until `matures_at` / payout |
| `started_at` / `matures_at` | Term window |

```bash
curl -X POST "{{baseUrl}}investment-packages/1/invest" \
  -H "Authorization: Bearer {{userToken}}" \
  -H "Content-Type: application/json" \
  -d '{"amount_usd": 1000}'
```

### Daily return escrow (implemented)

| Rule | Detail |
|------|--------|
| Escrow | Return-only pot on the holding (`accrued_return_usd`). Does **not** change spendable wallet during the term |
| Day 1 | **Next** calendar day after `started_at` (app timezone). Subscribe day earns `$0`. Exactly `term_days` logs: `startDate+1` … `startDate+term_days` |
| Daily amount | Flat: `expected_return_amount_usd / term_days` (2dp). Last day takes leftover cents so logs sum exactly to expected return |
| Log | One row per day in daily-earnings; unique per investment + date |
| Maturity | After `matures_at` and all term days logged → credit **principal + accrued return** to wallet (`investment_payout_credit`); set `payout_completed_at` and `status: ended` |
| Scheduler | `investments:end-due` accrues + settles (idempotent). Member show/list also accrues for that user before respond |

### GET {{baseUrl}}investments

Query: `page`, `per_page`, `sort_by=newest|oldest`, `status=active|ended`.

**`meta.summary`:** `active`, `ended`, `total`.

```bash
curl -X GET "{{baseUrl}}investments?status=active&page=1&per_page=10" \
  -H "Authorization: Bearer {{userToken}}"
```

### GET {{baseUrl}}investments/{id}

Owner-only. **404** `"Investment not found"` for other users' holdings.

Includes escrow fields above.

```bash
curl -X GET "{{baseUrl}}investments/1" \
  -H "Authorization: Bearer {{userToken}}"
```

### GET {{baseUrl}}investments/{id}/daily-earnings

Owner-only paginated daily log.

Query: `page`, `per_page`, `sort_by=newest|oldest` (by `earning_date`; default `newest`).

| Field | Notes |
|-------|-------|
| `earning_date` | `Y-m-d` |
| `amount_usd` | That day’s return slice |
| `accrued_return_after_usd` | Escrow total after this day |

```bash
curl -X GET "{{baseUrl}}investments/1/daily-earnings?page=1&per_page=10&sort_by=oldest" \
  -H "Authorization: Bearer {{userToken}}"
```

---

## Still later

| Area | Notes |
|------|-------|
| Admin end holding early | Admin action to flip holding to `ended` |

---

## Admin package investors (implemented)

Powers admin package detail → **who invested** table.

| Screen area | Endpoint |
|-------------|----------|
| Investors list | `GET {{baseUrl}}admin/investment-packages/{id}/investments` |
| Filter options | `GET {{baseUrl}}admin/investment-packages/{id}/investments/filter-options` |

Auth: **admin JWT**.

### Filter options

```
GET {{baseUrl}}admin/investment-packages/{id}/investments/filter-options
```

Structured filters only: `status` (`active` \| `ended`). **No** search in filter-options.

```bash
curl -X GET "{{baseUrl}}admin/investment-packages/1/investments/filter-options" \
  -H "Authorization: Bearer {{adminToken}}"
```

### List

```
GET {{baseUrl}}admin/investment-packages/{id}/investments?page=1&per_page=10&sort_by=newest&status=&search=
```

| Query | Notes |
|-------|-------|
| `page` / `per_page` | Pagination (max 100) |
| `sort_by` | `newest` \| `oldest` by `started_at` — default `newest` |
| `status` | Optional `active` \| `ended` |
| `search` | Free-text on investor email / first_name / last_name (list only) |

**`meta.summary`:** `active`, `ended`, `total` for this package.

**`data[]` row:** holding fields + nested `user` `{ id, first_name, last_name, email }`.

```bash
curl -X GET "{{baseUrl}}admin/investment-packages/1/investments?page=1&per_page=10&sort_by=newest" \
  -H "Authorization: Bearer {{adminToken}}"
```

---

## Locked impl defaults (walkthrough closed)

| Topic | Decision |
|-------|----------|
| Field list | This doc is source of truth for Phase B admin CRUD; FE rename later if sheet differs |
| Summary chips | Global counts by **stored** status (not effective/capacity) |
| Auto-expire | Scheduled command **+** persist-on-read safety |
| Delete `data` | `null` |
| Invest / holdings | Implemented — member catalog, invest, my investments |
| Daily escrow | Flat daily return; pay principal + accrued at maturity only |
