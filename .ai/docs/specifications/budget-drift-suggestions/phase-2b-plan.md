# Phase 2b plan

Branch `feat/budget-suggestions-phase-2b`, from `feat/budget-suggestions` (phase 2a merged, `73c5da2f`).

## Files

- **`app/Http/Requests/API/BudgetSuggestionsRequest.php`** (new, same folder as `BudgetChartRequest`). Rules:
    - `category_ids` nullable array, `category_ids.*` integer, existing in `categories` for the current user (another user's id gives 422, not an empty result).
    - `all` nullable boolean.
    - `data_through` nullable, `date_format:Y-m`.
    - `sensitivity` nullable, in `low|medium|high`; `bias` nullable, in `neutral|conservative` (the service silently falls back on invalid values, so the request is the validator).
    - After-validation: exactly one of `category_ids` (non-empty) or `all=1` is required.
- **`ReportApiController::budgetSuggestions()`**: thin. Injects `BudgetSuggestionService`, calls `run($user, $all ? null : $ids, ...)`, returns `response()->json($result)`. Add the action to the class-level `abilities:read` `only:` list.
- **`routes/api.php`**: `GET /reports/budget-suggestions`, name `reports.budget-suggestions`, next to `reports.budget-chart`.
- **`app/Console/Commands/ReviewBudgetSuggestions.php`**: `budget-suggestions:review {user} {--through=} {--sensitivity=} {--bias=} {--json} {--backtest}`, in the repo's attribute style (`#[Signature]`/`#[Description]`).
    - `{user}` is the user id; unknown id fails like `RecalculatePayeeProfiles`. Options are validated with the same allowed values as the request (sensitivity `low|medium|high`, bias `neutral|conservative`, `--through` as `Y-m`).
    - Default run: `run($user, null, ...)` (Review all). `--json` prints `json_encode` of the service result unchanged, so it matches the endpoint's body for the same input (the only difference is `computed_at`). Without `--json`: a table (category, verdict, type, current, suggested, effective date, k of n) plus a verdict count line and the elapsed seconds. The elapsed time goes to the table output only, never into `--json`. *(Changed 2026-11-13: the table follows the service order, which ranks suggestions by monthly impact, and adds "Monthly impact" and "% of monthly expenses" columns.)*
    - `--backtest`: for each of the last 24 months as `data_through`, run the detector and count suggestions per category per year. Prints a table (category, count per year, flagged when more than 2). With `--json`, prints the same data as JSON. Sequential runs, so about 24 × 3 s.
- **`.ai/docs/specifications/budget-drift-suggestions/mcp-integration.md`**: the future `GetBudgetSuggestions` read tool (parameters mapped 1:1 to the endpoint, output schema = the response contract), an optional `ReplaceBudget` write tool, and the notes an MCP author needs (read-only, ability, amounts as JSON numbers). Documentation only, no code.
- Decisions log: any deviation, plus the full "Review all" runtime re-measure the spec asked for in 2b.

## Tests (Pest, Feature)

- **`tests/Feature/API/ReportApiBudgetSuggestionsTest.php`**
    - Contract: `run`, `units`, `series` shape; `series` present for `category_ids[]`, `null` for `all=1`.
    - Validation: neither parameter, both parameters, bad `data_through`, bad sensitivity, another user's category id.
    - Two identical calls give identical `key`s.
    - Writes nothing: a `DB::listen` collector asserts no insert/update/delete statement during a call.
    - The recommended series equals the series of a saved-then-projected Budget (save the proposal as a real row, re-read `current` from the service, compare with the earlier `recommended`).
- **`ApiAbilityEnforcementTest`**: one row, `'reports.budget-suggestions' => ['api.v1.reports.budget-suggestions', 'get', 'read', []]`. The existing provider needs a valid request, so I will check whether the empty-params call passes validation before the ability check (middleware runs first, so deny works; the allow case may need the `all=1` query). If the provider cannot carry a query string, I add the allow case as a separate test in the new file.
- **`tests/Feature/Console/ReviewBudgetSuggestionsCommandTest.php`**: `--json` output equals the endpoint body for the same input (ignoring `computed_at`); invalid user and invalid option fail; table mode lists a suggestion fixture; `--backtest` runs on a small fixture and reports counts per year. The detector fixtures from `BudgetSuggestionServiceTest` are reused where possible.

Run: `vendor/bin/sail artisan test --compact --filter='BudgetSuggestion|ApiAbilityEnforcement'`, then `--filter=BudgetChart`. Then Pint and PHPStan.

## Unclear spec points (my proposed answers; please confirm or correct)

1. **Parameter style**: `category_ids[]` and `all=1`, exactly one required, as the spec's endpoint table says. `BudgetChartRequest` uses `categories`; I follow the spec for this new endpoint.
2. **`{user}` argument**: the numeric user id, not the email.
3. **"Output matches the endpoint's"**: byte-equal JSON apart from `computed_at`. Both call the same service method, so there is no separate serializer.
4. **Backtest definition of "per year"**: calendar year of the simulated `data_through` month, over the last 24 months. A category counts a suggestion when its verdict is `suggestion` in that run; the same drift repeating in consecutive months therefore counts several times, which is the instability the tuning is meant to expose. A dedupe by `key` would hide exactly that, so I do not dedupe.
5. **Full-run re-measure** (the open item from the decisions log): measured through the command (table mode prints elapsed time), cold and warm cache, on the dev database. Recorded in the handoff, not in `--json`.

## Out of scope for 2b

Tuning `config/yaffa.php` (the owner's checkpoint after this phase), any UI, translations, and the queued fallback.
