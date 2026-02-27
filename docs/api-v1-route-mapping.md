# API V1 Route Inventory & Mapping Table

This document maps all legacy API routes to their V1 equivalents.

## Legend
- **migrate**: Route exists in both legacy and V1; V1 is the canonical version.
- **keep**: Route not yet migrated; legacy path remains authoritative for now.

---

## CurrencyRate (Migrated - Wave 1)

| Legacy Route | Method | Target V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/currency-rates/{from}/{to}` | GET | `/api/v1/currency-rates/{from}/{to}` | migrate | `api.v1.currency-rates.index` |
| `/api/currency-rates` | POST | `/api/v1/currency-rates` | migrate | `api.v1.currency-rates.store` |
| `/api/currency-rates/{currency_rate}` | PUT | `/api/v1/currency-rates/{currencyRate}` | migrate | `api.v1.currency-rates.update` |
| `/api/currency-rates/{currency_rate}` | DELETE | `/api/v1/currency-rates/{currencyRate}` | migrate | `api.v1.currency-rates.destroy` |
| `/api/currencyrates/missing/{currency}` | GET | `/api/v1/currency-rates/{currency}/retrieve-missing` | migrate | Changed to POST (non-idempotent) |

## InvestmentPrice (Migrated - Wave 1)

| Legacy Route | Method | Target V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/investment-prices/{investment}` | GET | `/api/v1/investment-prices/{investment}` | migrate | `api.v1.investment-prices.index` |
| `/api/investment-prices` | POST | `/api/v1/investment-prices` | migrate | `api.v1.investment-prices.store` |
| `/api/investment-prices/{investment_price}` | PUT | `/api/v1/investment-prices/{investmentPrice}` | migrate | `api.v1.investment-prices.update` |
| `/api/investment-prices/{investment_price}` | DELETE | `/api/v1/investment-prices/{investmentPrice}` | migrate | `api.v1.investment-prices.destroy` |
| `/api/investment-prices/missing/{investment}` | GET | `/api/v1/investment-prices/{investment}/retrieve-missing` | migrate | Changed to POST (non-idempotent) |
| `/api/investment-prices/check/{investment}` | GET | `/api/v1/investment-prices/{investment}/check` | migrate | `api.v1.investment-prices.check` |

## AiProviderConfig (Migrated - Wave 2)

| Legacy Route | Method | Target V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/ai/config` | GET | `/api/v1/ai/config` | migrate | `api.v1.ai.config.show` |
| `/api/ai/config` | POST | `/api/v1/ai/config` | migrate | `api.v1.ai.config.store` |
| `/api/ai/config/{aiProviderConfig}` | PATCH | `/api/v1/ai/config/{aiProviderConfig}` | migrate | `api.v1.ai.config.update` |
| `/api/ai/config/{aiProviderConfig}` | DELETE | `/api/v1/ai/config/{aiProviderConfig}` | migrate | `api.v1.ai.config.destroy` |
| `/api/ai/test` | POST | `/api/v1/ai/config/test` | migrate | Normalized under `/ai/config` prefix |

## GoogleDriveConfig (Migrated - Wave 2)

| Legacy Route | Method | Target V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/google-drive/config` | GET | `/api/v1/google-drive/config` | migrate | `api.v1.google-drive.config.show` |
| `/api/google-drive/config` | POST | `/api/v1/google-drive/config` | migrate | `api.v1.google-drive.config.store` |
| `/api/google-drive/config/{googleDriveConfig}` | PATCH | `/api/v1/google-drive/config/{googleDriveConfig}` | migrate | `api.v1.google-drive.config.update` |
| `/api/google-drive/config/{googleDriveConfig}` | DELETE | `/api/v1/google-drive/config/{googleDriveConfig}` | migrate | `api.v1.google-drive.config.destroy` |
| `/api/google-drive/test` | POST | `/api/v1/google-drive/config/test` | migrate | Normalized under `/google-drive/config` prefix |
| `/api/google-drive/sync/{googleDriveConfig}` | POST | `/api/v1/google-drive/config/{googleDriveConfig}/sync` | migrate | Follows `{resource}/{id}/{action}` convention |

## Deferred Resources (Keep Legacy for Now)

| Legacy Route | Method | Planned V1 Route | Status |
|---|---|---|---|
| `/api/assets/account` | GET | `/api/v1/accounts` | keep |
| `/api/assets/account/{accountEntity}` | GET | `/api/v1/accounts/{accountEntity}` | keep |
| `/api/assets/accountentity/{accountEntity}` | DELETE | `/api/v1/account-entities/{accountEntity}` | keep |
| `/api/assets/accountgroup/{accountGroup}` | DELETE | `/api/v1/account-groups/{accountGroup}` | keep |
| `/api/assets/category` | GET/POST | `/api/v1/categories` | keep |
| `/api/assets/category/{category}` | GET/DELETE | `/api/v1/categories/{category}` | keep |
| `/api/assets/investment` | GET | `/api/v1/investments` | keep |
| `/api/assets/investment/{investment}` | GET/DELETE | `/api/v1/investments/{investment}` | keep |
| `/api/assets/investmentgroup/{investmentGroup}` | DELETE | `/api/v1/investment-groups/{investmentGroup}` | keep |
| `/api/assets/payee` | GET/POST | `/api/v1/payees` | keep |
| `/api/assets/payee/{accountEntity}` | GET | `/api/v1/payees/{payee}` | keep |
| `/api/assets/tag` | GET | `/api/v1/tags` | keep |
| `/api/assets/tag/{tag}` | GET | `/api/v1/tags/{tag}` | keep |
| `/api/budgetchart` | GET | `/api/v1/reports/budget-chart` | keep |
| `/api/reports/cashflow` | GET | `/api/v1/reports/cashflow` | keep |
| `/api/transactions` | GET | `/api/v1/transactions` | keep |
| `/api/transaction/{transaction}` | GET/DELETE | `/api/v1/transactions/{transaction}` | keep |
| `/api/transactions/standard` | POST | `/api/v1/transactions/standard` | keep |
| `/api/transactions/investment` | POST | `/api/v1/transactions/investment` | keep |
| `/api/onboarding/{topic}` | GET | `/api/v1/onboarding/{topic}` | keep |
| `/api/user/settings` | PATCH | `/api/v1/user/settings` | keep |
| `/api/user/preference/{key}` | GET/PUT | `/api/v1/user/preferences/{key}` | keep |
| `/api/documents` | GET/POST | `/api/v1/documents` | keep |
| `/api/documents/{aiDocument}` | GET/PATCH/DELETE | `/api/v1/documents/{aiDocument}` | keep |
| `/api/ai/payees/{payee}/category-stats` | GET | `/api/v1/payees/{payee}/category-stats` | keep |
