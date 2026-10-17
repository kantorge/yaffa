# Test Coverage: Remote Select

## Existing coverage

| Use case → rule | Expected (+ deny) | Evidence | Type | CI |
|---|---|---|---|---|
| Payee create page redirects to the modal | 302 → `?type=payee&create=1` | `tests/Feature/PayeeFormRedirectTest.php` | feature | Feature |
| Payee edit page redirects after authz | Own payee → 302 `&edit={id}`. Foreign payee → 403, and a web PATCH doesn't change the name | `PayeeFormRedirectTest.php` | feature | Feature |
| Legacy web payee writes are closed | POST/PATCH with `config_type=payee` → 404, no row written or changed | `PayeeFormRedirectTest.php` | feature | Feature |
| Payee API validation | Empty name → 422 on create and update, nothing written | `tests/Feature/API/PayeeApiValidationTest.php` | feature | Feature |
| Payee API update authz | Foreign payee → denied | `PayeeApiControllerTest::test_user_cannot_update_other_users_payee` | feature | Feature |
| Payee endpoints honour token abilities | Read/write token deny + allow | `tests/Feature/API/ApiAbilityEnforcementTest.php` | feature | Feature |
| Payee merge authz | Foreign payee → denied (form + submit) | `tests/Feature/PayeeTest.php` | feature | Feature |
| `user_id` defaults even when the model booted unauthenticated | Created model gets the acting user's id. An explicit id isn't overridden | `tests/Feature/ModelOwnedByUserTraitBootOrderTest.php` | feature | Feature |
| Custom `renderOption`/`renderItem` escape API data | Investment named `<img src=x onerror=…>` renders as text in the option and the selected item, and the handler doesn't run | `TransactionFormInvestmentStandaloneTest.php` (~line 410) | browser E2E | `test-e2e.yml` |
| Query-string semantics (`undefined` omitted, `null` empty) | Exact serialized string | `resources/js/shared/lib/tom-select/queryString.test.js` | JS unit (`node --test`) | **not in CI** |
| Every migrated select: search, select, clear, presets, modals, deep links, merge exclusion, investment currency filter, Tab/click-away never selects | UI behaviour per spec AC | `tests/PEST/**` (Pest browser, 90 pass / 3 todo at the last full run) | browser E2E | `test-e2e.yml` on PRs |

## Proposed tests

| Rule | Expected | Type |
|---|---|---|
| `GET /api/v1/payees/{id}` denies a foreign payee (the edit deep link now depends on it) | 403 | feature |
| Preferred ∩ not-preferred categories rejected server-side | 422 when the same id is in both lists. **Fails today** (see Gaps) | feature |
| Run `queryString.test.js` in CI | `node --test` step in `test-unit.yml` | CI |

## Gaps

1. **Preferred/excluded overlap is unverified and unenforced server-side.**
   `AccountEntityRequest` `Rule::notIn('config.not_preferred')` compares against the literal string.
   Verified: the validator accepts `{p:[5], n:[5]}`. It predates this branch and only affects the
   user's own data.
2. Three browser cases are `->todo()`: submit with schedule (standard and investment), multiple
   transaction items.

## Recommended CI gate

Unit and Feature (`test-unit.yml`, `test-feature.yml`) plus E2E (`test-e2e.yml`) green before merging
to `develop`. Add the `node --test` step so the JS unit test gates too.
