# Phase 2b handoff

## Built
- `GET /api/v1/reports/budget-suggestions` (`api.v1.reports.budget-suggestions`): `ReportApiController::budgetSuggestions()`, class-level `abilities:read` list extended. Validation in `App\Http\Requests\API\BudgetSuggestionsRequest`.
- `app/Console/Commands/ReviewBudgetSuggestions.php`: `budget-suggestions:review {user} {--through=} {--sensitivity=} {--bias=} {--json} {--backtest}`.
- `mcp-integration.md` (documentation only).
- `ApiAbilityEnforcementTest` row `reports.budget-suggestions`.

## Interfaces for the next phase
- Query: `category_ids[]` (user-owned ids) xor `all=1`; optional `data_through` (`Y-m`), `sensitivity` (`low|medium|high`), `bias` (`neutral|conservative`). 422 otherwise.
- Response body is `BudgetSuggestionService::run()` as documented in the 2a handoff, JSON-encoded unchanged. `series` is `null` for `all=1`.
- Command `--json` output decodes to the same data as the endpoint body apart from `computed_at` (the command pretty-prints). `--through` cannot be combined with `--backtest`.

## Deviations from the spec
Decisions log: ownership validation of `category_ids`; backtest counting rule (no dedupe, flag at more than 2 per year).

## Measurements
- Dev database (user 1, 92 units): full "Review all" through the command: 4.13 s cold cache, 1.86 s warm. Under the 5 s target, but the cold run is close; the queued fallback stays in the backlog. The query count was not measured.
- `--backtest` (24 runs): 45 s. Result: 36 categories got a suggestion at least once, 24 flagged with more than 2 per year (several with 6-8). The default thresholds are too jumpy: this is the owner checkpoint's tuning input.

## Tests
- `vendor/bin/sail artisan test --compact --filter='ReportApiBudgetSuggestions|ReviewBudgetSuggestions|ApiAbilityEnforcement'`: 182 pass (new: 18 in the two new files).
- Pint and PHPStan (whole `app/`) are clean. The full suite was not run.

## Known issues and open questions
- Owner checkpoint: tune `config/yaffa.php` with `--backtest`, record final values in the decisions log. Suggested first look: the repeated suggestions suggest persistence/tolerance are loose, or that applied-and-unapplied suggestions repeat each month by design (nothing is stored, so an unapplied suggestion re-appears monthly).
- The 2a open points still stand (horizon cut at `end_date`, counted rrules, mixed-sign netting).
