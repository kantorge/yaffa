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

## Accounts (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/account` | GET | `/api/v1/accounts` | migrate | `api.v1.accounts.index` |
| `/api/assets/account/investment` | GET | `/api/v1/accounts/investment` | migrate | `api.v1.accounts.investment` |
| `/api/assets/account/{accountEntity}` | GET | `/api/v1/accounts/{accountEntity}` | migrate | `api.v1.accounts.show` |
| `/api/account/balance/{accountEntity?}` | GET | `/api/v1/accounts/{accountEntity}/balance` | migrate | `api.v1.accounts.balance` |
| `/api/account/monthlySummary/{accountEntity}` | PUT | `/api/v1/accounts/{accountEntity}/monthly-summary` | migrate | `api.v1.accounts.monthly-summary` |

## AccountEntity (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/accountentity/{accountEntity}/active/{active}` | PUT | `PATCH /api/v1/account-entities/{accountEntity}` + `{active:bool}` body | migrate | `api.v1.account-entities.patch-active` — body-based active toggle |
| `/api/assets/accountentity/{accountEntity}` | DELETE | `/api/v1/account-entities/{accountEntity}` | migrate | `api.v1.account-entities.destroy` |

## AccountGroup (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/accountgroup/{accountGroup}` | DELETE | `/api/v1/account-groups/{accountGroup}` | migrate | `api.v1.account-groups.destroy` |

## Categories (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/category` | GET | `/api/v1/categories` | migrate | `api.v1.categories.index` |
| `/api/assets/categories` | GET | `/api/v1/categories?withInactive=1` | migrate | Folded into index via query param |
| `/api/assets/category` | POST | `/api/v1/categories` | migrate | `api.v1.categories.store` |
| `/api/assets/category/{category}/active/{active}` | PUT | `PATCH /api/v1/categories/{category}` + `{active:bool}` body | migrate | `api.v1.categories.patch-active` |
| `/api/assets/category/{category}` | GET | `/api/v1/categories/{category}` | migrate | `api.v1.categories.show` |
| `/api/assets/category/{category}` | DELETE | `/api/v1/categories/{category}` | migrate | `api.v1.categories.destroy` |

## Investments (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/investment` | GET | `/api/v1/investments` | migrate | `api.v1.investments.index` |
| `/api/assets/investment/timeline` | GET | `/api/v1/investments/timeline` | migrate | `api.v1.investments.timeline` |
| `/api/assets/investment/{investment}` | GET | `/api/v1/investments/{investment}` | migrate | `api.v1.investments.show` |
| `/api/assets/investment/{investment}/active/{active}` | PUT | `PATCH /api/v1/investments/{investment}` + `{active:bool}` body | migrate | `api.v1.investments.patch-active` |
| `/api/assets/investment/price/{investment}` | GET | `/api/v1/investments/{investment}/price-history` | migrate | `api.v1.investments.price-history` |
| `/api/assets/investment/{investment}` | DELETE | `/api/v1/investments/{investment}` | migrate | `api.v1.investments.destroy` |

## InvestmentGroup (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/investmentgroup/{investmentGroup}` | DELETE | `/api/v1/investment-groups/{investmentGroup}` | migrate | `api.v1.investment-groups.destroy` |

## Payees (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/payee` | GET | `/api/v1/payees` | migrate | `api.v1.payees.index` |
| `/api/assets/payee` | POST | `/api/v1/payees` | migrate | `api.v1.payees.store` |
| `/api/assets/payee/similar` | GET | `/api/v1/payees/similar` | migrate | `api.v1.payees.similar` |
| `/api/assets/payee/{accountEntity}` | GET | `/api/v1/payees/{payee}` | migrate | `api.v1.payees.show` |
| `/api/assets/get_default_category_suggestion` | GET | `/api/v1/payees/category-suggestions/default` | migrate | `api.v1.payees.category-suggestions.default` |
| `/api/assets/accept_default_category_suggestion/{payee}/{category}` | GET | `POST /api/v1/payees/{payee}/category-suggestions/accept/{category}` | migrate | `api.v1.payees.category-suggestions.accept` — changed to POST |
| `/api/assets/dismiss_default_category_suggestion/{payee}` | GET | `POST /api/v1/payees/{payee}/category-suggestions/dismiss` | migrate | `api.v1.payees.category-suggestions.dismiss` — changed to POST |
| `/api/ai/payees/{payee}/category-stats` | GET | `/api/v1/payees/{payee}/category-stats` | migrate | `api.v1.payees.category-stats` |

## Tags (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/assets/tag` | GET | `/api/v1/tags` | migrate | `api.v1.tags.index` |
| `/api/assets/tag/{tag}` | GET | `/api/v1/tags/{tag}` | migrate | `api.v1.tags.show` |
| `/api/assets/tag/{tag}/active/{active}` | PUT | `PATCH /api/v1/tags/{tag}` + `{active:bool}` body | migrate | `api.v1.tags.patch-active` |

## Transactions (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/transactions` | GET | `/api/v1/transactions` | migrate | `api.v1.transactions.index` |
| `/api/transactions/get_scheduled_items/{type}` | GET | `GET /api/v1/transactions/scheduled-items?type=...` | migrate | `api.v1.transactions.scheduled-items` — type moved to query param |
| `/api/transactions/standard` | POST | `/api/v1/transactions/standard` | migrate | `api.v1.transactions.store-standard` |
| `/api/transactions/investment` | POST | `/api/v1/transactions/investment` | migrate | `api.v1.transactions.store-investment` |
| `/api/transactions/standard/{transaction}` | PATCH | `/api/v1/transactions/standard/{transaction}` | migrate | `api.v1.transactions.update-standard` |
| `/api/transactions/investment/{transaction}` | PATCH | `/api/v1/transactions/investment/{transaction}` | migrate | `api.v1.transactions.update-investment` |
| `/api/transactions/{transaction}/skip` | PATCH | `/api/v1/transactions/{transaction}/skip` | migrate | `api.v1.transactions.skip` |
| `/api/transaction/{transaction}` | GET | `/api/v1/transactions/{transaction}` | migrate | `api.v1.transactions.show` — normalized to plural |
| `/api/transaction/{transaction}/reconciled/{newState}` | PUT | `PATCH /api/v1/transactions/{transaction}/reconciliation` + `{reconciled:bool}` body | migrate | `api.v1.transactions.reconcile` — state in body, not URL |
| `/api/transaction/{transaction}` | DELETE | `/api/v1/transactions/{transaction}` | migrate | `api.v1.transactions.destroy` — normalized to plural |

## Reports (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/budgetchart` | GET | `/api/v1/reports/budget-chart` | migrate | `api.v1.reports.budget-chart` |
| `/api/reports/cashflow` | GET | `/api/v1/reports/cashflow` | migrate | `api.v1.reports.cashflow` |
| `/api/reports/waterfall/{transactionType}/{dataType}/{year}/{month?}` | GET | `/api/v1/reports/waterfall/{...}` | migrate | `api.v1.reports.waterfall` |

## Onboarding (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/onboarding/{topic}` | GET | `/api/v1/onboarding/{topic}` | migrate | `api.v1.onboarding.show` |
| `/api/onboarding/{topic}/dismiss` | PUT | `POST /api/v1/onboarding/{topic}/dismiss` | migrate | `api.v1.onboarding.dismiss` — changed to POST |
| `/api/onboarding/{topic}/complete-tour` | PUT | `POST /api/v1/onboarding/{topic}/complete-tour` | migrate | `api.v1.onboarding.complete-tour` — changed to POST |

## User / Settings (Migrated - Wave 3)

| Legacy Route | Method | V1 Route | Status | Notes |
|---|---|---|---|---|
| `/api/user/settings` | PATCH | `/api/v1/users/me/settings` | migrate | `api.v1.users.me.settings` |
| `/api/user/change_password` | PATCH | `/api/v1/users/me/password` | migrate | `api.v1.users.me.password` — renamed from change_password |
| `/api/user/preference/{key}` | GET | `/api/v1/users/me/preferences/{key}` | migrate | `api.v1.users.me.preferences.get` |
| `/api/user/preference/{key}` | PUT | `/api/v1/users/me/preferences/{key}` | migrate | `api.v1.users.me.preferences.set` |

