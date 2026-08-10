# Crypto MTM investment API (frontend contract)

Status: **implemented**  
Audience: **frontend / QA** — wire from this file  
Last updated: 2026-08-09  

`{{baseUrl}}` includes `/api/` (e.g. `https://novacoinv2-backend.test/api/`).

**Related (unchanged):**  
- Fixed-% packages: [investment-packages-api-contract.md](./investment-packages-api-contract.md)  
- Wallet balance / deposits: [user-wallet-funding-api-contract.md](./user-wallet-funding-api-contract.md)  
- Short product summary: [crypto-investment-infra-plan.md](./crypto-investment-infra-plan.md)  

---

## What this feature is

**Crypto mark-to-market (MTM) investment** lets a member put **USD from their wallet** against a **real cryptocurrency’s live USD price** (CoinGecko), hold that exposure for a fixed term, then get paid whatever the position is worth at the end.

It is **price exposure only** — the app does **not** buy/send on-chain coins. Units are virtual:  
`units = committed_usd ÷ entry_price_usd`, then each day escrow ≈ `units × that day’s price` (with optional max-loss floor).

### Who does what

| Role | Does |
|------|------|
| **Admin** | Turns product on/off; sets term length, fee, min/max invest, max-loss rules; picks which coins (from live top 30) members may invest in. **Does not create “packages.”** |
| **Member** | Picks an enabled coin → sees live price → chooses amount + who pays the fee → confirms → wallet debited → holding opens. Watches daily up/down escrow. At term end, final escrow credits spendable wallet once. |

### Member journey (plain)

1. Open **Crypto** invest hub (separate from Fixed % plans).  
2. See list of **admin-enabled coins** + **current USD price**, **24h change**, and **logo** (all from our API — **do not call CoinGecko from the browser**).  
3. Tap a coin → enter USD amount → choose fee source → quote → confirm.  
4. During term: holding escrow moves with the market; spendable wallet does not get daily credits.  
5. After term: system pays **current escrow** into the wallet once.

### Admin journey (plain)

1. Open **Crypto investment settings** (one form).  
2. Configure term / fee / max loss / min-max / enabled.  
3. Multi-select supported coins from live CoinGecko top 30.  
4. Optionally open **all crypto holdings** table.

---

## Auth

| Surface | Header |
|---------|--------|
| Admin | `Authorization: Bearer {{adminToken}}` |
| Member | `Authorization: Bearer {{userToken}}` (+ not banned/suspended) |

Unauthenticated → **401**. Envelope: `status`, `message`, `data`, `errors`, optional `meta`.

---

## Admin

| UI | Endpoint |
|----|----------|
| Settings form | `GET/PUT {{baseUrl}}admin/crypto-investment-settings` |
| Coin multi-select | `GET {{baseUrl}}admin/crypto-investment-settings/coin-options` |
| All holdings | `GET {{baseUrl}}admin/crypto-investments` |
| Holdings filters | `GET {{baseUrl}}admin/crypto-investments/filter-options` |

**PUT settings fields:** `is_enabled`, `term_days`, `minimum_amount_usd`, `maximum_amount_usd`, `fee_type` (`fixed_usd`\|`percent`), `fee_value`, `max_loss_enabled`, `max_loss_percent` (`gt:0` `lte:50`), `supported_asset_ids` (CoinGecko ids from coin-options).

```bash
curl -X GET "{{baseUrl}}admin/crypto-investment-settings" \
  -H "Authorization: Bearer {{adminToken}}"

curl -X PUT "{{baseUrl}}admin/crypto-investment-settings" \
  -H "Authorization: Bearer {{adminToken}}" \
  -H "Content-Type: application/json" \
  -d '{
    "is_enabled": true,
    "term_days": 30,
    "minimum_amount_usd": 50,
    "maximum_amount_usd": 100000,
    "fee_type": "percent",
    "fee_value": 1,
    "max_loss_enabled": true,
    "max_loss_percent": 50,
    "supported_asset_ids": ["bitcoin", "ethereum", "solana"]
  }'
```

---

## Member — assets / quote / invest / holdings

| UI | Endpoint |
|----|----------|
| Settings (copy / disabled) | `GET {{baseUrl}}crypto-investment-settings` |
| Coin catalog + market UI | `GET {{baseUrl}}crypto-investment-assets` |
| Coin price chart history | `GET {{baseUrl}}crypto-investment-assets/{coingecko_asset_id}/price-history?range=` |
| Quote | `GET {{baseUrl}}crypto-investment-assets/{coingecko_asset_id}/invest-quote?amount_usd=&fee_charge_source=` |
| Invest | `POST {{baseUrl}}crypto-investment-assets/{coingecko_asset_id}/invest` |
| My holdings | `GET {{baseUrl}}crypto-investments` |
| Holding detail | `GET {{baseUrl}}crypto-investments/{id}` |
| Daily MTM log | `GET {{baseUrl}}crypto-investments/{id}/daily-valuations` |

### Asset catalog (`GET crypto-investment-assets`)

Backend batch-fetches CoinGecko markets for supported ids (cached ~5 min) and returns change + logo. **SPA must not call `api.coingecko.com` for invest/dashboard.**

**`data.assets[]` row**

| Field | Type | Notes |
|-------|------|-------|
| `coingecko_asset_id` | string | Route key for quote/invest |
| `asset_symbol` / `asset_label` | string | Display |
| `current_price_usd` | number \| null | Invest truth (aligned with quote/invest cache when markets succeed) |
| `price_change_percentage_24h` | number \| null | Signed %, e.g. `0.2`, `-1.35`. Null if CG miss |
| `image_url` | string \| null | Coin logo URL. Null if missing |
| `can_invest` | bool | `is_enabled && current_price_usd !== null` |

Also on `data`: `is_enabled`, `term_days`, min/max, fee, max-loss settings.

```json
{
  "coingecko_asset_id": "bitcoin",
  "asset_symbol": "BTC",
  "asset_label": "Bitcoin",
  "current_price_usd": 65131,
  "price_change_percentage_24h": 0.2,
  "image_url": "https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png",
  "can_invest": true
}
```

Partial CG failure → null change/image (and price fallback via simple price when possible); whole catalog still **200**.

### Price history (`GET crypto-investment-assets/{coingecko_asset_id}/price-history`)

Member chart time-series. Backend owns CoinGecko `market_chart`; SPA must not call CG.

| Query | Required | Values | Default |
|-------|----------|--------|---------|
| `range` | no | `24h` \| `7d` \| `30d` \| `1y` | `7d` |

- Coin must be in **supported** allowlist → else **422** on `coingecko_asset_id`
- Invalid `range` → **422** on `range`
- Chart allowed even when `is_enabled` is false (browse UX); invest still gated
- CG miss → **200** with `points: []`
- Cached per `(asset_id, range)` (~5 min for `24h`/`7d`, ~30 min for `30d`/`1y`; empty → ~45s)

**`data`**

| Field | Notes |
|-------|--------|
| `coingecko_asset_id` / `asset_symbol` / `asset_label` | Echo + snapshot |
| `range` | Echo |
| `currency` | Always `usd` |
| `points` | Oldest → newest; `{ t` ISO-8601 UTC, `price_usd` }; capped ≤ 300 |

```bash
curl -X GET "{{baseUrl}}crypto-investment-assets/bitcoin/price-history?range=7d" \
  -H "Authorization: Bearer {{userToken}}"

curl -X GET "{{baseUrl}}crypto-investment-assets/bitcoin/price-history?range=24h" \
  -H "Authorization: Bearer {{userToken}}"
```

### Quote / invest

`fee_charge_source`: `from_invest_amount` \| `from_wallet`

| Source | Committed | Wallet debit |
|--------|-----------|--------------|
| `from_invest_amount` | amount − fee (> 0) | amount |
| `from_wallet` | amount | amount + fee |

```bash
curl -X GET "{{baseUrl}}crypto-investment-assets" \
  -H "Authorization: Bearer {{userToken}}"

curl -X GET "{{baseUrl}}crypto-investment-assets/bitcoin/invest-quote?amount_usd=5000&fee_charge_source=from_wallet" \
  -H "Authorization: Bearer {{userToken}}"

curl -X POST "{{baseUrl}}crypto-investment-assets/bitcoin/invest" \
  -H "Authorization: Bearer {{userToken}}" \
  -H "Content-Type: application/json" \
  -d '{"amount_usd": 5000, "fee_charge_source": "from_wallet"}'
```

### Holding fields (member)

`id`, asset snapshots, `amount_usd`, fee snapshots, `committed_usd`, `entry_price_usd`, `units`, `current_escrow_usd`, `current_price_usd`, `unrealized_pnl_usd`, max-loss snapshot, term dates, `status` / `effective_status` (+ labels).

Daily valuation: `valuation_date`, `price_usd`, escrow before/after, `delta_usd` (can be negative), `was_clamped_by_max_loss`.

---

## Product rules for copy

1. Returns follow market price; optional max-loss floor; escrow never &lt; $0.  
2. First MTM day = next calendar day after invest.  
3. During term, MTM does not credit spendable wallet.  
4. At maturity, wallet gets final escrow once.  
5. `is_enabled: false` → disable invest UI.

---

## FE checklist

- [ ] Fixed Plans vs Crypto Plans are separate  
- [ ] Admin = one settings page (no package CRUD)  
- [ ] Catalog reads `price_change_percentage_24h` + `image_url` from our API  
- [ ] Coin detail chart uses `price-history` (no browser CG)  
- [ ] **Zero** browser calls to CoinGecko for invest/dashboard  
- [ ] Quote refresh on amount / fee source change  
- [ ] Holdings show PnL + daily up/down table  
