# NovaCoin Investment Platform — Product & Domain Spec

Status: draft (documentation only — not implemented yet)  
Audience: engineering & product  
Last updated: 2026-08-01

> **Phase B API (admin package CRUD):** [investment-packages-api-contract.md](./investment-packages-api-contract.md)  
> **Wallet funding API:** [user-wallet-funding-api-contract.md](./user-wallet-funding-api-contract.md)  
> **Naming:** *Investment plan* in this doc ≡ *investment package* in the Phase B contract.  
> **Deferred here for later:** member invest / holdings from balance; older crypto-invest-without-wallet flow.

---

## 1. Summary

NovaCoin is an investment platform where:

1. Users choose an investment plan and send **crypto** to a platform wallet.
2. Admins **manually verify** the transfer and **approve** (or reject) the investment.
3. Approved investments become visible on the user side as **active**.
4. When an investment **matures**, admins **fund** the user’s account (credit principal + return).

Payment method for this version: **crypto transfer only** (no card/bank rails).

---



## 2. Actors


| Actor     | Responsibility                                                                                                        |
| --------- | --------------------------------------------------------------------------------------------------------------------- |
| **User**  | Browse plans, submit investment with crypto payment proof/reference, view own investments and wallet balance          |
| **Admin** | Manage investment plans, manage supported crypto wallets, review/approve/reject investments, fund matured investments |


---



## 3. End-to-end flows



### 3.1 Invest (user → pending)

```
User selects InvestmentPlan
  → enters amount (within plan min/max)
  → chooses supported CryptoWallet (network + asset)
  → sends crypto to that wallet address (off-platform)
  → submits investment with transfer reference / proof
  → Investment status = PendingApproval
```

User cannot treat the investment as active until admin approval.

### 3.2 Approve (admin → active)

```
Admin reviews pending Investment
  → verifies on-chain / exchange that crypto arrived
  → Approves → status = Active
       · records approved_at
       · computes matures_at from plan duration
       · investment becomes visible as active for the user
  OR Rejects → status = Rejected
       · optional rejection_reason
```



### 3.3 Mature (time)

```
Active investment reaches matures_at
  → status = AwaitingPayout (or Matured)
  → appears in admin “ready to fund” queue
```

Maturity detection may be a scheduled job later; funding remains **manual admin action** in this version.

### 3.4 Fund (admin → credit user)

```
Admin funds matured Investment
  → credits UserWallet with principal + return (per plan rules)
  → writes WalletLedgerEntry (immutable ledger row)
  → Investment status = PaidOut
  → user sees updated wallet balance
```

---



## 4. Domain entities

Names below are proposed engineering names (descriptive, stable). UI copy may differ.

### 4.1 InvestmentPlan

Admin-defined product users can subscribe to.


| Field                       | Meaning                                                                |
| --------------------------- | ---------------------------------------------------------------------- |
| `name`                      | Display name                                                           |
| `description`               | Optional marketing/copy                                                |
| `minimum_investment_amount` | Lowest allowed principal                                               |
| `maximum_investment_amount` | Highest allowed principal (nullable = no cap)                          |
| `return_percentage`         | Return at maturity (e.g. 10 = 10%) — exact formula TBD before build    |
| `duration_days`             | Days from approval until maturity                                      |
| `is_active`                 | Whether users can still subscribe                                      |
| `currency_code`             | Accounting currency for amounts (e.g. USDT) — align with crypto assets |


**Open decision:** return = fixed % of principal at maturity only (recommended v1), vs daily accrual.

### 4.2 SupportedCryptoWallet

Platform receive addresses configured by admin. Users must send to one of these.


| Field            | Meaning                                               |
| ---------------- | ----------------------------------------------------- |
| `name`           | Label for admins/users (e.g. “USDT — TRC20 Treasury”) |
| `asset_symbol`   | e.g. `USDT`, `BTC`, `ETH`                             |
| `network_name`   | e.g. `TRC20`, `ERC20`, `Bitcoin`, `BEP20`             |
| `wallet_address` | Public receive address                                |
| `is_active`      | Shown to users only when active                       |
| `sort_order`     | Display order                                         |
| `notes`          | Internal admin notes (optional)                       |


Rules:

- Only **active** wallets appear on the user invest form.
- An investment stores which wallet the user claimed to pay to (`supported_crypto_wallet_id`).
- Deactivating a wallet does **not** alter historical investments that used it.
- Admin can create / update / deactivate wallets. Address changes for an existing wallet should be rare; prefer deactivate + create new.



### 4.3 Investment

One user subscription attempt / position.


| Field                        | Meaning                                                  |
| ---------------------------- | -------------------------------------------------------- |
| `user_id`                    | Owner                                                    |
| `investment_plan_id`         | Plan at time of subscribe                                |
| `supported_crypto_wallet_id` | Wallet user was instructed / claimed to pay              |
| `principal_amount`           | Amount invested                                          |
| `expected_return_amount`     | Computed return (snapshot at approve or at create — TBD) |
| `expected_payout_amount`     | Principal + return (snapshot)                            |
| `status`                     | See status machine                                       |
| `crypto_transfer_reference`  | Tx hash / memo / reference user provides                 |
| `crypto_transfer_proof_path` | Optional uploaded proof (screenshot)                     |
| `rejection_reason`           | Set when rejected                                        |
| `submitted_at`               | When user created the request                            |
| `approved_at`                | When admin approved                                      |
| `rejected_at`                | When admin rejected                                      |
| `matures_at`                 | Approval time + plan duration                            |
| `paid_out_at`                | When admin funded wallet                                 |
| `approved_by_admin_user_id`  | Audit                                                    |
| `paid_out_by_admin_user_id`  | Audit                                                    |




### 4.4 Investment status machine

```
PendingApproval
  → Active          (admin approve)
  → Rejected        (admin reject)

Active
  → AwaitingPayout  (maturity reached)

AwaitingPayout
  → PaidOut         (admin funds user wallet)
```

Terminal states: `Rejected`, `PaidOut`.

Optional later: `Cancelled` (user/admin before approval).

### 4.5 UserWallet

Per-user balance container (one wallet per user for v1 is enough).


| Field               | Meaning                                                             |
| ------------------- | ------------------------------------------------------------------- |
| `user_id`           | Owner (unique)                                                      |
| `available_balance` | Spendable / withdrawable credit (withdrawal product may come later) |
| `currency_code`     | Same accounting currency as plans                                   |


Balance must only change via ledger entries — never “silent” updates.

### 4.6 WalletLedgerEntry

Immutable money movement log.


| Field                      | Meaning                                                                                         |
| -------------------------- | ----------------------------------------------------------------------------------------------- |
| `user_wallet_id`           | Target wallet                                                                                   |
| `entry_type`               | e.g. `InvestmentPayoutCredit`, later `WithdrawalDebit`, `AdjustmentCredit`                      |
| `amount`                   | Positive for credit, negative for debit (or amount + direction enum — pick one pattern at impl) |
| `balance_after`            | Snapshot after apply                                                                            |
| `investment_id`            | Nullable FK when entry comes from investment payout                                             |
| `description`              | Human-readable reason                                                                           |
| `created_by_admin_user_id` | Nullable; set for admin-driven credits                                                          |
| `created_at`               | When applied                                                                                    |




### 4.7 User (customers — `users` table)

Separate from admins. No role column.


| Field                     | Meaning                                              |
| ------------------------- | ---------------------------------------------------- |
| `first_name`, `last_name` | Profile name                                         |
| `email`                   | Unique login / Google email                          |
| `password`                | Nullable (null for Google-only accounts)             |
| `phone`                   | Optional                                             |
| `google_id`               | Nullable unique Google subject                       |
| `email_verified_at`       | Set on Google login when Google marks email verified |


Auth: JWT (`api` guard). Login via email/password or Google ID token (`POST /api/auth/google`).

### 4.8 Admin (backoffice — `admins` table)


| Field                     | Meaning                                           |
| ------------------------- | ------------------------------------------------- |
| `first_name`, `last_name` | Profile name                                      |
| `email`                   | Unique login                                      |
| `password`                | Required (email/password only — no Google)        |
| `phone`                   | Optional                                          |
| `is_super_admin`          | One seeded super admin; API never creates another |


Auth: JWT (`admin` guard). No public register; seeded super admin; any admin may create further admins.

---



## 5. Crypto payment specifics



### 5.1 How payment works

1. Admin maintains the list of **SupportedCryptoWallet** records.
2. On invest, user picks a wallet (asset + network) and sees the **wallet address** to pay.
3. User sends crypto **outside** the app (their exchange/wallet).
4. User submits:
  - amount
  - plan
  - selected platform wallet
  - transfer reference (tx hash preferred)
  - optional proof image
5. Admin verifies receipt manually (block explorer / exchange), then approves.

There is **no automated chain watcher** in v1.

### 5.2 Risks / rules to document in product copy

- Wrong network → funds may be lost; UI must show network + address clearly.
- Amount mismatch → admin may reject or approve at verified amount (policy TBD).
- Duplicate tx hash → reject / block second investment using same reference.

---



## 6. Visibility rules


| Status            | User sees?                        | Admin sees?        |
| ----------------- | --------------------------------- | ------------------ |
| `PendingApproval` | Yes (as pending)                  | Yes (review queue) |
| `Rejected`        | Yes (as rejected + reason if set) | Yes                |
| `Active`          | Yes                               | Yes                |
| `AwaitingPayout`  | Yes (matured / awaiting payout)   | Yes (fund queue)   |
| `PaidOut`         | Yes (history)                     | Yes                |


User never sees other users’ investments or platform wallet private keys (we only store receive addresses).

---



## 7. Admin capabilities (v1 checklist)

- [ ] CRUD / activate InvestmentPlan
- [ ] CRUD / activate SupportedCryptoWallet (asset, network, address)
- [ ] List PendingApproval investments → approve / reject
- [ ] List AwaitingPayout investments → fund (credit UserWallet + ledger)
- [ ] View user wallet balances and ledger
- [x] Authenticate as Admin (JWT email/password)
- [x] Create additional admins



## 8. User capabilities (v1 checklist)

- [x] Register / login (JWT email/password + Google ID token)
- [ ] List active InvestmentPlans
- [ ] List active SupportedCryptoWallets (public fields only)
- [ ] Create Investment (pending)
- [ ] List own Investments
- [ ] View own UserWallet balance + ledger

Out of scope for v1 unless added later: automated withdrawals, KYC, multi-currency wallets, on-chain auto-confirm, referrals.

---



## 9. Payout calculation (proposed v1)

On approve (or on create — prefer **on approve** so admin can adjust if verified amount differs):

```
expected_return_amount  = principal_amount * (return_percentage / 100)
expected_payout_amount  = principal_amount + expected_return_amount
matures_at              = approved_at + duration_days
```

On fund:

```
credit UserWallet.available_balance by expected_payout_amount
write WalletLedgerEntry (InvestmentPayoutCredit)
mark Investment PaidOut
```

**Open decision:** if admin verifies a different on-chain amount than submitted principal, allow admin to set `principal_amount` before approve.

---



## 10. Suggested API surface (sketch only)

Not implemented yet. Naming for future Laravel API:

**Auth (implemented)**

- `POST /api/auth/register|login|google` · `POST /api/auth/logout|refresh` · `GET /api/auth/me`
- `POST /api/admin/auth/login` · `POST /api/admin/auth/logout|refresh` · `GET /api/admin/auth/me`
- `GET|POST /api/admin/admins`

Auth mechanism: JWT (`php-open-source-saver/jwt-auth`), dual guards (`api` / `admin`). Users may use Google ID token exchange; admins email/password only.

**User (investment domain — not implemented yet)**

- `GET /api/investment-plans`
- `GET /api/supported-crypto-wallets`
- `POST /api/investments`
- `GET /api/investments`
- `GET /api/investments/{investment}`
- `GET /api/wallet`
- `GET /api/wallet/ledger-entries`

**Admin (investment domain — not implemented yet)**

- `POST|PUT|PATCH /api/admin/investment-plans...`
- `POST|PUT|PATCH /api/admin/supported-crypto-wallets...`
- `GET /api/admin/investments?status=pending_approval`
- `POST /api/admin/investments/{investment}/approve`
- `POST /api/admin/investments/{investment}/reject`
- `GET /api/admin/investments?status=awaiting_payout`
- `POST /api/admin/investments/{investment}/fund`

---



## 11. Open decisions (resolve before coding)

1. **Return model:** fixed % at maturity only? (recommended)
2. **Principal mismatch:** reject vs admin-adjusted principal on approve
3. **Proof upload:** required or optional?
4. **Currency:** single stablecoin accounting (e.g. USDT only) vs multi-asset plans
5. **Withdrawals:** in v1 or later?
6. **Admin UI:** API-only for separate SPA, or Laravel Blade/Filament admin?
7. **Maturity job:** auto-flip `Active` → `AwaitingPayout`, or compute “matured” from `matures_at` on read + admin fund gate?

Resolved: auth = JWT + separate `users`/`admins` tables; Google for users only.

---



## 12. Implementation order (when coding starts)

1. ~~Auth + Admin/User role~~ (done: dual JWT tables + Google for users)
2. SupportedCryptoWallet
3. InvestmentPlan
4. Investment create + admin approve/reject
5. UserWallet + WalletLedgerEntry
6. Maturity → admin fund
7. Tests for status transitions and ledger integrity

---



## 13. Non-goals (v1)

- Automated blockchain confirmation
- Custodial hot-wallet sending from the app
- Interest compounding mid-term
- Marketplace / secondary sale of investments
- Fiat on-ramps

