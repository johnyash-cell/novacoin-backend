# Crypto price-linked investments

**Status:** implemented (settings + live assets — **no admin packages**)  
**Audience:** frontend / QA / backend  
**Last updated:** 2026-08-09  

**Related:**  
- API contract: [crypto-investment-api-contract.md](./crypto-investment-api-contract.md)  
- Fixed-% packages (unchanged): [investment-packages-api-contract.md](./investment-packages-api-contract.md)  
- Wallet funding: [user-wallet-funding-api-contract.md](./user-wallet-funding-api-contract.md)  

---

## Product summary

| | Fixed-% packages | Crypto MTM (this) |
|--|------------------|-------------------|
| Catalog | Admin-created packages | **Admin settings** + **supported real coins** |
| Invest target | Package row | Live CoinGecko asset + USD amount |
| Return | Fixed % daily escrow | Mark-to-market vs live price |
| Fee / term / max loss | Per package | **Global settings** (snapshotted onto holding) |

**Flow**

1. Admin sets term, fee, max loss, min/max, enabled flag, and which top-30 coins are investable.
2. Member opens supported assets → sees live USD prices.
3. Quote → invest → wallet debit; holding opens at entry price.
4. Daily MTM updates escrow; payout at maturity only.
