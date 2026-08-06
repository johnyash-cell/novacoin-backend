# User wallet withdrawal API (request → admin payout → approve)

Status: **implemented**  
Audience: **frontend / QA**  
Last updated: 2026-08-06  



## Summary

Member requests a USD payout from wallet balance to their own crypto address. Balance is **debited immediately** (held). Admin sends crypto **manually** off-platform, then approves (optional tx reference). Decline **refunds** the held USD.

| Screen area | Endpoint | Auth |
|-------------|----------|------|
| Payout methods | `GET /platform-crypto-wallets?purpose=withdrawal` | User JWT |
| Available balance | `GET /wallet` | User JWT |
| Submit withdrawal | `POST /wallet/withdrawals` | User JWT |
| Withdrawal history | `GET /wallet/withdrawals` | User JWT |
| History filters | `GET /wallet/withdrawals/filter-options` | User JWT |
| Admin review list | `GET /admin/wallet-withdrawals` | Admin JWT |
| Approve / decline | `POST /admin/wallet-withdrawals/{id}/approve\|decline` | Admin JWT |

`{{baseUrl}}` includes API prefix (e.g. `https://novacoinv2-backend.test/api/`).

---

## Envelope

Same NovaCoin envelope (`status`, `message`, `data`, `errors`, optional `meta.pagination` / `meta.filters`).

---

## Locked product rules

| Topic | Rule |
|-------|------|
| Member payload | `usd_amount`, `platform_crypto_wallet_id`, `destination_wallet_address` |
| Amount | USD |
| Payout methods | `is_available_for_withdrawal = true` only |
| Crypto amount | Locked at submit via CoinGecko (`crypto_amount_expected`) for admin to send |
| Balance on submit | Debited immediately (`withdrawal_debit` ledger) |
| Admin approve | Manual crypto send outside app; API only marks approved (+ optional tx ref). **No** further balance change |
| Admin decline | Credits held USD back (`withdrawal_refund_credit`) |
| Reference | Auto `WW-YYYYMMDD-XXXXXXXX` |

---

## Member — payout methods

```
GET {{baseUrl}}platform-crypto-wallets?purpose=withdrawal
```

See funding contract. Only wallets with `is_available_for_withdrawal = true`.

---

## Member — submit withdrawal

```
POST {{baseUrl}}wallet/withdrawals
```

| Field | Required | Rules |
|-------|----------|-------|
| `usd_amount` | yes | number `> 0`; must not exceed available balance |
| `platform_crypto_wallet_id` | yes | must exist and be available for withdrawal |
| `destination_wallet_address` | yes | string, max 255 |

```bash
curl -X POST "{{baseUrl}}wallet/withdrawals" \
  -H "Authorization: Bearer {{userToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "usd_amount": 500,
    "platform_crypto_wallet_id": 1,
    "destination_wallet_address": "bc1qmemberdestination"
  }'
```

### Withdrawal object (member)

| Field | Notes |
|-------|-------|
| `id` | |
| `reference_number` | e.g. `WW-20260806-A7K3X9B2` — use in History “Reference” |
| `usd_amount` | Debited on submit |
| `crypto_amount_expected` | Amount admin should send |
| `conversion_rate_usd_per_unit` | Locked rate |
| `asset_symbol` / `network_name` | Snapshots |
| `platform_crypto_wallet_id` | Payout method |
| `destination_wallet_address` | Member receive address |
| `status` | `pending_approval` \| `approved` \| `declined` |
| `status_label` | Human label |
| `decline_reason` | When declined |
| `outbound_transaction_reference` | When admin set on approve |
| `reviewed_at` / `created_at` | |

Insufficient balance → **422** on `usd_amount`.  
Price unavailable → **422** on `platform_crypto_wallet_id`.

---

## Member — withdrawal history

```
GET {{baseUrl}}wallet/withdrawals?page=1&per_page=10&sort_by=newest&status=
GET {{baseUrl}}wallet/withdrawals/filter-options
```

Own withdrawals only. Filter-options: `status` single-select.

---

## Admin — wallet withdrawals

```
GET {{baseUrl}}admin/wallet-withdrawals/filter-options
GET {{baseUrl}}admin/wallet-withdrawals?page=1&per_page=10&sort_by=newest&status=pending_approval&search=
GET {{baseUrl}}admin/wallet-withdrawals/{id}
```

Includes user name/email + destination address + crypto amount to send.

### Approve

```
POST {{baseUrl}}admin/wallet-withdrawals/{id}/approve
```

| Field | Required | Default | Notes |
|-------|----------|---------|-------|
| `outbound_transaction_reference` | no | — | Tx hash / memo after manual payout |
| `send_email` | no | `false` | Queue email to member when true |
| `send_in_app_notification` | no | `false` | Create member inbox notification when true |

Idempotent if already approved (no second email/notification). Only from `pending_approval`. **Does not** change balance (already debited). Notify failures are logged and never block the admin response.

```bash
curl -X POST "{{baseUrl}}admin/wallet-withdrawals/1/approve" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "outbound_transaction_reference": "btc-txid-abc123",
    "send_email": true,
    "send_in_app_notification": true
  }'
```

### Decline

```
POST {{baseUrl}}admin/wallet-withdrawals/{id}/decline
```

| Field | Required | Default | Notes |
|-------|----------|---------|-------|
| `decline_reason` | no | — | Shown to member in copy when set |
| `send_email` | no | `false` | Queue email to member when true |
| `send_in_app_notification` | no | `false` | Create member inbox notification when true |

Refunds held USD. Idempotent if already declined (no second email/notification). Cannot decline after approved.

```bash
curl -X POST "{{baseUrl}}admin/wallet-withdrawals/1/decline" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "decline_reason": "Destination address invalid",
    "send_email": true,
    "send_in_app_notification": true
  }'
```

---

## Admin — platform wallet flag

Configure payout methods with `is_available_for_withdrawal` on create/update of `/admin/platform-crypto-wallets` (see funding contract).
