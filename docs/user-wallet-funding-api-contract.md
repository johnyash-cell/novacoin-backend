# User wallet funding API (crypto deposit → admin approve → USD balance)

Status: **implemented** — invest-from-balance still later  
Audience: **frontend / QA**  
Last updated: 2026-08-04  

Product notes: [user-wallet-funding-plan.md](./user-wallet-funding-plan.md)

---

## Summary

Admin configures which cryptocurrencies users may pay with (BTC, ETH, USDT, …) plus receive address. Member Fund Account: enter **USD**, pick an admin wallet, see live crypto amount (CoinGecko), upload proof. Admin approves → credit **USD** to member wallet balance.

| Screen area | Endpoint | Auth |
|-------------|----------|------|
| Admin crypto wallet list / CRUD | `/admin/platform-crypto-wallets…` | Admin JWT |
| Admin deposit review | `GET /admin/wallet-deposits` | Admin JWT |
| Approve / decline | `POST /admin/wallet-deposits/{id}/approve\|decline` | Admin JWT |
| Available balance | `GET /wallet` | User JWT |
| Payment methods | `GET /platform-crypto-wallets` | User JWT |
| Live quote | `GET /wallet/deposit-quote` | User JWT |
| Submit deposit + proof | `POST /wallet/deposits` | User JWT |
| My deposit history | `GET /wallet/deposits` | User JWT |

`{{baseUrl}}` includes API prefix (e.g. `https://novacoinv2-backend.test/api/`).

---

## Envelope

Same NovaCoin envelope (`status`, `message`, `data`, `errors`, optional `meta.pagination` / `meta.filters`).

---

## Platform crypto wallet model

Admin-defined funding option. **Not** a hardcoded catalog — admin chooses which assets exist.

| Field | Type | Notes |
|-------|------|-------|
| `id` | integer | |
| `name` | string | Display label (defaults to asset label if omitted on create) |
| `asset_key` | string | Admin-facing pick from asset-options (e.g. `bitcoin`) — admin responses only |
| `asset_symbol` | string | Derived e.g. BTC, ETH, USDT |
| `coingecko_asset_id` | string | Derived for pricing — admin responses only; members never send/see this |
| `network_name` | string | e.g. Bitcoin, ERC20, TRC20 |
| `wallet_address` | string | Receive address |
| `is_available_for_funding` | boolean | User-visible when true |
| `sort_order` | integer | Ascending |
| `notes` | string \| null | Admin-only |
| `created_at` / `updated_at` | ISO-8601 | |

Member list omits `notes`, `asset_key`, `coingecko_asset_id`.

---

## Admin — platform crypto wallets

### Asset options (friendly list for create/edit)

```
GET {{baseUrl}}admin/platform-crypto-wallets/asset-options
```

Admin picks `value` as `asset_key`. Backend maps to symbol + CoinGecko id. Admin does **not** type CoinGecko ids.

```json
{
  "status": true,
  "message": "Platform crypto asset options retrieved successfully",
  "data": {
    "assets": [
      { "value": "bitcoin", "label": "Bitcoin", "asset_symbol": "BTC" },
      { "value": "ethereum", "label": "Ethereum", "asset_symbol": "ETH" },
      { "value": "tether", "label": "USDT", "asset_symbol": "USDT" }
    ],
    "total_available_assets": 10
  },
  "errors": null
}
```

### Filter options

```
GET {{baseUrl}}admin/platform-crypto-wallets/filter-options
```

Filters: `is_available_for_funding` single-select.

### List

```
GET {{baseUrl}}admin/platform-crypto-wallets?page=1&per_page=10&sort_by=newest&search=&is_available_for_funding=
```

### Create

```
POST {{baseUrl}}admin/platform-crypto-wallets
```

| Field | Required |
|-------|----------|
| `asset_key` | yes — from asset-options (`bitcoin`, `ethereum`, …) |
| `network_name` | yes |
| `wallet_address` | yes |
| `name` | no — defaults to asset label (e.g. Bitcoin) |
| `is_available_for_funding` | no (default true) |
| `sort_order` | no (default 0) |
| `notes` | no |

### Show / Update / Delete

```
GET|PUT|PATCH|DELETE {{baseUrl}}admin/platform-crypto-wallets/{id}
```

```bash
curl -X POST "{{baseUrl}}admin/platform-crypto-wallets" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "asset_key": "bitcoin",
    "network_name": "Bitcoin",
    "wallet_address": "bc1qexample",
    "is_available_for_funding": true,
    "sort_order": 1
  }'
```

---

## User — wallet balance

```
GET {{baseUrl}}wallet
```

```json
{
  "status": true,
  "message": "Wallet fetched successfully",
  "data": {
    "available_balance": 0,
    "currency_code": "USD"
  },
  "errors": null
}
```

Wallet is created lazily (balance `0`) on first access.

---

## User — available payment methods

```
GET {{baseUrl}}platform-crypto-wallets
```

Only rows with `is_available_for_funding = true`, ordered by `sort_order` then `id`. Includes `wallet_address` and `network_name` for pay instructions.

---

## User — deposit quote (live rate)

```
GET {{baseUrl}}wallet/deposit-quote?usd_amount=1000&platform_crypto_wallet_id=1
```

Uses CoinGecko; caches ~60s. Wallet must be available for funding.

```json
{
  "status": true,
  "message": "Deposit quote fetched successfully",
  "data": {
    "platform_crypto_wallet_id": 1,
    "asset_symbol": "BTC",
    "network_name": "Bitcoin",
    "wallet_address": "bc1qexample",
    "usd_amount": 1000,
    "conversion_rate_usd_per_unit": 63719.19,
    "crypto_amount": 0.01569412,
    "quoted_at": "2026-08-04T08:00:00.000000Z"
  },
  "errors": null
}
```

If price unavailable → **503** human message.

---

## User — submit deposit

```
POST {{baseUrl}}wallet/deposits
Content-Type: multipart/form-data
```

| Field | Required | Rules |
|-------|----------|-------|
| `usd_amount` | yes | number `> 0` |
| `platform_crypto_wallet_id` | yes | must exist and be available for funding |
| `proof_image` | yes | image png/jpg/jpeg/webp, max 5120 KB |

Locks live rate at submit. Status = `pending_approval`. **Does not** credit balance.

```bash
curl -X POST "{{baseUrl}}wallet/deposits" \
  -H "Authorization: Bearer {{userToken}}" \
  -F "usd_amount=1000" \
  -F "platform_crypto_wallet_id=1" \
  -F "proof_image=@/path/to/proof.png"
```

### Deposit object (user)

| Field | Notes |
|-------|-------|
| `id` | |
| `reference_number` | Human-facing txn-style id, e.g. `WD-20260806-A7K3X9B2` — use this in History “Reference”, not `#id` |
| `usd_amount` | Credited on approve |
| `crypto_amount_expected` | Amount user was told to send |
| `conversion_rate_usd_per_unit` | Locked rate |
| `asset_symbol` / `network_name` / `wallet_address` | Snapshots |
| `status` | `pending_approval` \| `approved` \| `declined` |
| `status_label` | Human label |
| `proof_image_url` | Public URL |
| `decline_reason` | When declined |
| `created_at` | |

---

## User — deposit history

```
GET {{baseUrl}}wallet/deposits?page=1&per_page=10&sort_by=newest&status=
```

Own deposits only.

---

## Admin — wallet deposits

```
GET {{baseUrl}}admin/wallet-deposits?page=1&per_page=10&sort_by=newest&status=pending_approval&search=
```

Includes user name/email + proof URL.

### Approve

```
POST {{baseUrl}}admin/wallet-deposits/{id}/approve
```

Credits **`usd_amount`** to user wallet via ledger. Idempotent if already approved. Only from `pending_approval`.

### Decline

```
POST {{baseUrl}}admin/wallet-deposits/{id}/decline
```

Body optional: `{ "decline_reason": "…" }`. No balance change.

```bash
curl -X POST "{{baseUrl}}admin/wallet-deposits/1/approve" \
  -H "Authorization: Bearer {{adminToken}}"
```

---

## Later

Member invest from `available_balance` into investment packages — separate pass.
