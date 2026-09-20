# Recurrence Storage Simplification (RRULE) + Month-End Patterns — Specification Addendum

This document extends [specification.md](specification.md) (the shipped 4.0.0 budget/schedule
redesign). It supersedes an earlier draft of this addendum that proposed two more component
columns (`days_before_month_end`, `last_business_day_of_month`) on top of `by_day`/`by_month`.
That draft is not implemented and is replaced by this one before any code was written. See
[architecture.md](../../features/budget-schedule-redesign/architecture.md)/`RecurrenceRuleService`
for the base mechanism this addendum reshapes.

**Status: implemented** (backend, migrations, frontend, tests, docs), in the still-unreleased 4.0.0
line. Written as a pre-implementation handoff; Section 14's checklist records what has landed.
Still open: FR-16's one-off personal-instance steps, the frontend rebuild/manual UI check, and the
final quality gates. This was a `release/v4` change, and v4 has not shipped to any external user —
this is the deliberate timing this addendum relies on (Section 7).

## 1. Purpose and Motivation

The original ask was two more recurrence patterns ("N days before month end," "last working day
of month"). The straightforward implementation would have added a third and fourth column
(`by_day`/`by_month` were themselves added once already, in `2026_08_04_000001_...`) to both
`transaction_schedules` and `budgets`, each threaded through `RecurrenceRuleService`'s entire
positional-parameter method list, the shared validation trait, and the Vue pattern picker.

That is the same shape of change every previous recurrence feature has required, and the pattern
doesn't stop here — `BYYEARDAY`, `BYWEEKNO`, multi-day `BYDAY` sets, or any other RFC 5545 feature
would each repeat it: new column(s) on two tables, new parameters threaded through six
`RecurrenceRuleService` methods and every call site, new validation-trait rules, new frontend
plumbing. YAFFA is hand-decomposing RFC 5545 into columns one feature at a time instead of storing
the thing RFC 5545 already is: **a single rule string**. `recurr/recurr` (already the only
recurrence engine in the codebase) is built entirely around parsing and serializing exactly that
string (`Rule::createFromString()` / `Rule::getString()`), so this isn't adopting a new format —
it's storing the format the library already speaks natively, instead of re-decomposing it into
columns and reassembling it on every read.

This addendum:

1. Replaces `frequency`/`interval`/`count`/`end_date`/`by_day`/`by_month` on both
   `transaction_schedules` and `budgets` with a single `rrule` column (the RFC 5545 RRULE
   parameter string, no `DTSTART`/`DTEND` embedded — see Section 3).
2. Re-expresses the two originally-requested month-end patterns as compositions of that string
   (`BYMONTHDAY`, `BYDAY`+`BYSETPOS`) needing **no schema of their own**.
3. Adds a real, shipping data-migration step (FR-15) that backfills `rrule` on
   `transaction_schedules` from every existing row's `frequency`/`interval`/`count`/`end_date`
   before those columns are dropped — required because, unlike `budgets` (new in this same
   branch), `transaction_schedules` predates 4.0 and already holds real operator data in those
   columns on every 3.x installation (see Section 7).

## 2. Feasibility (confirmed against the installed library)

- `Recurr\Rule::setByMonthDay()` / `getByMonthDay()` and `setBySetPosition()` /
  `getBySetPosition()` both already round-trip through `getString()`/`createFromString()` — this
  was independently confirmed by reading `vendor/simshaun/recurr/src/Recurr/Rule.php` (its own
  `getString()` implementation builds `BYMONTHDAY=`/`BYSETPOS=` parts unconditionally alongside
  `BYDAY=`/`BYMONTH=`; its `createFromString()` parses all of them back).
- `Rule::getString()` only emits `DTSTART=`/`DTEND=` when the rule was itself constructed by
  parsing a string that contained them (`isStartDateFromDtstart` flag). `RecurrenceRuleService::
  buildRule()` sets the start date programmatically (`->setStartDate(new DateTime(...))`), so the
  serialized string is guaranteed to contain only `FREQ`/`INTERVAL`/`UNTIL`/`COUNT`/`BYDAY`/
  `BYMONTH`/`BYMONTHDAY`/`BYSETPOS` — never a redundant, potentially-inconsistent copy of the start
  date. `Rule::createFromString($rrule, $startDate)` accepts the start date as a separate
  parameter for exactly this reason.
- No SQL-level query anywhere in `app/` filters/sorts/groups on `frequency`, `by_day`, `by_month`,
  `end_date`, or `count` (confirmed by grep — every read of these columns goes through
  `RecurrenceRuleService`, an Eloquent attribute access, or a Blade/API JSON pass-through). The
  one index touching this area (`transaction_schedules_active_next_date_index`, `(active,
  next_date)`) doesn't reference any of them either. Collapsing them into one column has no query
  or index fallout.

## 3. Goals

- **Storage-only simplification.** `transaction_schedules` and `budgets` each gain one `rrule`
  (text, not nullable) column and drop `frequency`, `interval`, `count`, `end_date`, `by_day`,
  `by_month`. `start_date` remains its own column (used independently of recurrence math —
  display, `next_date` defaults, `active`-flag window anchoring).
- **No change to the request/response contract.** `TransactionRequest`'s `schedule_config.*` and
  `BudgetRequest` keep validating the same discrete fields they do today
  (`frequency`/`interval`/`start_date`/`end_date`/`count`/`by_day`/`by_month`, plus the two
  month-end pattern fields below) — end users and API clients never see or supply a raw RRULE
  string. The Eloquent model absorbs the translation: virtual attributes
  (`Illuminate\Database\Eloquent\Casts\Attribute`) named `frequency`/`interval`/`count`/`end_date`/
  `by_day`/`by_month` read from and write into the single underlying `rrule` string. The Vue
  pattern picker (`TransactionSchedule.vue`, `BudgetForm.vue`, `Schedule.vue`) is unaffected by
  this document — it already speaks these discrete fields and continues to.
- **The two originally-requested patterns need no schema at all.** "N days before month end" and
  "last working day of month" become two more virtual, request-only fields
  (`days_before_month_end`, `last_business_day_of_month`) that compose into the same `rrule`
  string via the model's mutator layer — exactly like `by_day`/`by_month` do, except without ever
  touching the database schema.
- **`RecurrenceRuleService` is reshaped, not multiplied.** Its public methods currently take
  `(startDate, frequency, interval, endDate, count, byDay, byMonth, ...)` — 7 recurrence-shape
  parameters, one per column. They become `(startDate, rrule, ...)` — 1 parameter, a validated
  RRULE string — for every method (`getRecurrence()`, `hasOccurrenceOnOrAfter()`,
  `getOccurrencesAfter()`, `getRecurrenceBetween()`, `occursOn()`, and the `buildRule()` core).
  Adding a future RFC 5545 feature (`BYYEARDAY`, `BYWEEKNO`, …) after this addendum never touches
  this method list again — only the model's virtual-attribute composer/decomposer changes.
- **The server composes the canonical string; it never stores client-supplied RRULE text
  verbatim.** The mutator layer builds a `Recurr\Rule` from validated structured input and calls
  `getString()` on it — the stored string is always one this codebase's own `Rule` builder
  produced and Recurr itself can re-parse, never raw text carried through from a request body.

## 4. Non-Goals

- No raw-RRULE text box exposed to end users — the curated picker UI is unchanged (Non-Goal,
  carried over from the superseded draft).
- No support for arbitrary client-supplied RRULE strings, whether from the API or an import
  format — see Goals above; this is a deliberate security/robustness posture (RFC 5545 strings are
  effectively a small grammar Recurr must parse — accepting arbitrary text here is exactly the
  kind of externally-controlled-pattern surface the project is already careful about elsewhere,
  e.g. the QIF/CSV import `matching_rules` ReDoS note in the root `CLAUDE.md`).
- No change to `next_date`, `automatic_recording`, `inflation`, or any `Budget`-specific column
  (`category_id`, `account_id`, `transaction_type`, `amount`, `comment`) — these are unrelated to
  the recurrence *shape* and stay real columns.
- No change to `active`'s computation — `RecurrenceRuleService::hasOccurrenceOnOrAfter()` still
  drives it, just via the reshaped signature.
- No day-before-end / last-business-day validation beyond what the superseded draft already
  specified (0–27 range, weekday-only "business day" with no holiday calendar) — those semantics
  are unchanged, only their storage mechanism is.
- No raw-SQL / one-off backfill run by hand outside the migration system — FR-15's backfill is an
  ordinary Laravel migration, run by `php artisan migrate` like every other schema change, not a
  separately-invoked script (see Section 7; this corrects an earlier draft of this addendum, which
  wrongly assumed `transaction_schedules`' recurrence columns were unshipped like `budgets`).

## 5. Functional Requirements

### FR-10: Collapse recurrence columns into a single `rrule` column

`transaction_schedules` and `budgets` each replace `frequency`, `interval`, `count`, `end_date`,
`by_day`, `by_month` with one `rrule` column (`text`, not nullable — every schedule/budget has a
recurrence rule; there is no "no rule" state, matching today's `frequency` being required
already). `start_date` is untouched.

### FR-11: `RecurrenceRuleService` reshaped around RRULE strings

- `buildRule(Carbon $startDate, string $rrule): Rule` replaces the current 7-parameter version —
  implementation is `Rule::createFromString($rrule, new DateTime($startDate->toDateString()))`,
  no manual `setFreq()`/`setInterval()`/`setByDay()`/etc. assembly.
- `getRecurrence()`, `hasOccurrenceOnOrAfter()`, `getOccurrencesAfter()`, `getRecurrenceBetween()`,
  `occursOn()` each drop their `frequency`/`interval`/`endDate`/`count`/`byDay`/`byMonth`
  parameters in favor of the single `rrule` string, calling the new `buildRule()`.
- `estimatePeriodsBetween()` — still needs `frequency`/`interval` specifically (it's a cheap
  analytic estimate, deliberately not routed through the transformer/`Rule` object at all, per its
  existing docblock) — parses just the `FREQ=`/`INTERVAL=` tokens out of the `rrule` string (a
  trivial regex/`explode` on `;`, not a full `Rule::createFromString()` parse, to keep this
  synchronous-validation-path method as cheap as it is today) rather than taking them as separate
  parameters.

### FR-12: Model-level virtual attributes preserve the existing discrete field contract

`TransactionSchedule` and `Budget` each gain `Attribute::make()` accessors/mutators for
`frequency`, `interval`, `count`, `end_date`, `by_day`, `by_month` (get: decompose the stored
`rrule` string via `Rule::createFromString()`'s getters; set: read the model's other pending
virtual attributes, build a `Rule`, call `getString()`, write the result to the real `rrule`
attribute). These are **not** real columns and **not** independently persisted — setting
`$schedule->by_day = '1WE'` must be reflected in `$schedule->rrule` before save. `$fillable`
lists the virtual names, not `rrule`, so mass-assignment from `TransactionRequest`/`BudgetRequest`
continues to work unchanged; `rrule` itself is never client-fillable.

Because each virtual-attribute mutator needs the model's *other* pending recurrence fields to
compose a complete `Rule`, the mutators must read from `$this->attributes`/pending state rather
than re-deriving from the (possibly stale) already-persisted `rrule`, and the actual
compose-and-write-`rrule` step happens once, in a `saving()` model event (mirroring the existing
`booted()` pattern already used for `active`), not inside each individual attribute's mutator
closure independently.

### FR-13: Validation trait unchanged in spirit, updated in wiring

`ValidatesRecurrenceRule` continues to validate the same request-level fields
(`frequency`/`by_day`/`by_month`/the two month-end fields/`maxRecurrencePeriodsRule`) — validation
targets request input, which was never a database column even before this addendum, so this FR is
almost a no-op. The one real change: `maxRecurrencePeriodsRule()`'s closure currently calls
`RecurrenceRuleService::estimatePeriodsBetween($startDate, $frequency, $interval, ...)` directly
from already-separate request inputs — unchanged, since `frequency`/`interval` are still separate
request fields post-FR-12, only their *storage* is unified.

### FR-14: Month-end patterns as pure composition, no schema

Re-implements the superseded draft's FR-10/FR-11 (days-before-month-end, last-business-day) as
two virtual, request-only fields with no backing column on either table:

- `days_before_month_end` (validated `nullable|integer|between:0,27`, mutually exclusive with
  `by_day`/`last_business_day_of_month`, `MONTHLY`/`YEARLY`-only, pairs with `by_month` on
  `YEARLY`) — the model's `rrule`-composing `saving()` step calls
  `$rule->setByMonthDay([-($daysBeforeMonthEnd + 1)])`.
- `last_business_day_of_month` (validated `nullable|boolean`, same exclusivity/frequency/`by_month`
  rules) — composes `$rule->setByDay(['MO','TU','WE','TH','FR']); $rule->setBySetPosition([-1]);`.
- Both are exposed as *read-side* virtual attributes too (decomposed back out of a loaded `rrule`
  string — checking for `BYMONTHDAY` with a negative single value, or `BYSETPOS=[-1]` combined
  with the 5-weekday `BYDAY` set, respectively) so the edit form can re-populate correctly and
  `Schedule.vue`'s human-readable summary can describe the pattern.
- All exclusivity/frequency/`by_month`-pairing validation rules from the superseded draft's FR-12
  carry over unchanged — they operate on request input, unaffected by the storage change.

### FR-15: Real, shipping backfill migration for `transaction_schedules`

Unlike `budgets` (created fresh by this same branch, so no external installation has ever had a
`budgets` row), `transaction_schedules` predates this branch entirely: `frequency`/`interval`/
`count`/`end_date` are part of the schema every 3.x installation already ships with, and hold real
operator data. `by_day`/`by_month` are the only two columns on this table that are genuinely
unshipped (added, and now removed again, within this same unreleased line). Collapsing this
table's recurrence shape into `rrule` therefore needs a real, three-step migration sequence that
runs for every 3.x→4.0 upgrader — not a personal-instance-only script:

1. `2026_08_04_000001_add_rrule_to_transaction_schedules_table` — adds `rrule` (`text`, nullable
   for this transition only). This is the file that originally added `by_day`/`by_month`; since
   that addition never shipped, it's safe to rewrite in place (same "not yet tagged" reasoning as
   `budgets`) rather than layer a new migration on top of it.
2. `2026_08_04_000002_backfill_rrule_on_transaction_schedules_table` — for every row, reads the
   existing `frequency`/`interval`/`count`/`end_date` (and `by_day`/`by_month`, only present on a
   database old enough to have run step 1's original by_day/by_month-adding content — a genuine
   3.x upgrader never has them), builds a `Recurr\Rule` via a private, throwaway copy of the
   pre-FR-10 `buildRule()` assembly logic (that method's old 7-parameter shape no longer exists in
   `RecurrenceRuleService`), calls `getString()`, and writes it to `rrule`. Ships to every
   installation — this is real data, read directly via the query builder (not Eloquent, since the
   model layer's `HasRecurrenceRule` accessors no longer read these raw columns at all once FR-12
   lands). Reversible: `down()` just nulls `rrule` back out, since the old columns are still
   present and untouched at this point.
3. `2026_08_04_000003_drop_recurrence_columns_from_transaction_schedules_table` — guards first
   (refuses to proceed, throwing, if any row still has a null/empty `rrule` — mirrors
   `2026_08_05_000003`'s guard-before-DDL precedent, since MySQL DDL auto-commits and can't be
   rolled back if the guard ran after it), then drops `frequency`/`interval`/`count`/`end_date`
   unconditionally and `by_day`/`by_month` only if present, then makes `rrule` `NOT NULL`.

No separate Artisan command, no "delete before merge" lifecycle — this is ordinary migration
history, run automatically by `php artisan migrate` for every operator the same way the sibling
`budgets` conversion (`2026_08_05_000002`/`000003`) already is. See Section 7 for full ordering
against the `budgets` migrations.

### FR-16: Temporary, personal-instance-only backfill for `budgets` (non-shipping)

Discovered post-implementation, not part of the original addendum: the branch author's own
instance already ran the pre-rewrite `2026_08_05_000001_create_budgets_table` (old
frequency/interval/by_day/by_month/count/end_date columns) before this addendum rewrote that file
to create `rrule` directly (see Section 7's exception note). Since Laravel matches migrations by
filename, redeploying the rewritten file never re-runs it there, so that instance's `budgets` table
would otherwise stay in the old shape forever while every other part of the deployed code
(`Budget`'s `HasRecurrenceRule` wiring) assumes `rrule` exists — breaking every `Budget` read/write
outright.

Unlike FR-15, this genuinely **is** a one-off, non-shipping fix (no other installation has ever run
any shape of `create_budgets_table`, so no other installation can ever be in this state):

- `app:dev:migrate-budgets-recurrence-to-rrule` (`app/Console/Commands/MigrateBudgetsRecurrenceToRrule.php`)
  — no-ops immediately if `budgets.frequency` doesn't exist (i.e. this instance already has the
  rrule-based shape); otherwise adds `rrule` nullable if missing, then backfills every row from the
  old columns via the same private, throwaway `Recurr\Rule` assembly pattern as FR-15's original
  command.
- Reports migrated/failed counts, same as FR-15.
- Does **not** drop the old columns itself — the author does that manually (throwaway local
  migration or direct DDL) after verifying the backfill, then deletes this command. Never ships.

## 6. Data Model Changes

## 6. Data Model Changes

| Column | Before | After |
|---|---|---|
| `transaction_schedules.frequency` | `string`, required | removed — virtual attribute over `rrule` |
| `transaction_schedules.interval` | `int`, default 1 | removed — virtual attribute over `rrule` |
| `transaction_schedules.count` | `int`, nullable | removed — virtual attribute over `rrule` |
| `transaction_schedules.end_date` | `date`, nullable | removed — virtual attribute over `rrule` |
| `transaction_schedules.by_day` | `string(4)`, nullable | removed — virtual attribute over `rrule` |
| `transaction_schedules.by_month` | `unsignedTinyInteger`, nullable | removed — virtual attribute over `rrule` |
| `transaction_schedules.rrule` | — | **new**, `text`, not nullable |
| `budgets.*` (same 6 columns) | as `create_budgets_table` | same removal/replacement as above |
| `transaction_schedules.start_date` | `date` | unchanged |
| `transaction_schedules.next_date`, `automatic_recording`, `inflation` | unchanged | unchanged |
| `budgets.start_date`, `category_id`, `account_id`, `transaction_type`, `amount`, `comment`, `inflation`, `active` | unchanged | unchanged |

No `days_before_month_end`/`last_business_day_of_month` columns on either table (FR-14) — this is
the concrete payoff of FR-10, superseding the earlier draft's Section 6 table.

## 7. Migration Strategy

`transaction_schedules` and `budgets` are **not** the same case here, and the strategy differs
accordingly:

**`budgets` — rewrite in place (nothing has ever shipped *externally*).** `2026_08_05_000001_create_budgets_table.php`
is itself part of this unreleased branch; no installation outside this branch's own author has a
`budgets` table at all. It's edited directly to define `rrule` from the start, rather than
creating the old frequency/interval/by_day/by_month/count/end_date shape and layering a
drop-column migration on top of it that no real installation would ever need. This is the same
"not yet tagged" reasoning the base spec already relies on for pre-release schema edits — normal
"always additive, never rewrite a shipped migration" practice resumes the moment 4.0.0 actually
tags.

**Exception: the branch author's own personal instance already ran the pre-rewrite
`create_budgets_table`** (old frequency/interval/by_day/by_month/count/end_date columns), before
this addendum rewrote that file to create `rrule` directly. Laravel matches migrations by
filename, so redeploying the rewritten file to that instance never re-runs it — its `budgets`
table stays in the old shape indefinitely unless fixed separately. Unlike `transaction_schedules`,
this can *only* ever affect that one instance (no other installation has ever run any shape of
`create_budgets_table`), so it's fixed by a one-off, non-shipping Artisan command
(`app:dev:migrate-budgets-recurrence-to-rrule`, see FR-16) rather than a permanent guarded
migration — the same "temporary, delete once used" lifecycle originally (and wrongly) proposed for
`transaction_schedules` in an earlier draft of this addendum, correctly scoped this time to a
condition only one instance can ever hit.

**`transaction_schedules` — real backfill migration (FR-15), because this table predates 4.0.**
Unlike `budgets`, `transaction_schedules` is part of the schema every 3.x installation already
runs, and its `frequency`/`interval`/`count`/`end_date` columns hold real operator data — this is
not a greenfield table, and dropping those columns outright would destroy every existing
installation's schedules on upgrade. Only `by_day`/`by_month` (added, and now removed, entirely
within this unshipped branch) can be treated like `budgets`. The migration sequence is therefore
add → backfill → guarded drop (FR-15's three files), which runs identically for every 3.x
upgrader and for the branch author's own already-provisioned instance alike — there is no separate
personal-instance path or non-shipping script.

**Combined ordering** (by migration timestamp, all in the public history):

1. `2026_08_04_000001_add_rrule_to_transaction_schedules_table` — add `rrule` nullable.
2. `2026_08_04_000002_backfill_rrule_on_transaction_schedules_table` — backfill every row.
3. `2026_08_04_000003_drop_recurrence_columns_from_transaction_schedules_table` — guard, drop old
   columns, make `rrule` `NOT NULL`.
4. `2026_08_05_000001_create_budgets_table` — create `budgets` with `rrule` directly.
5. `2026_08_05_000002_transform_budget_transactions_to_budgets` — converts legacy budget-only
   transactions into `Budget` rows, reading `$schedule->frequency`/`interval`/`end_date`/`count`
   via `TransactionSchedule`'s FR-12 virtual accessors. This is exactly why steps 1–3 must fully
   complete first: those accessors decompose from `rrule`, so they only read correct values once
   `rrule` is backfilled and columns 4/5 (this step) run against a `TransactionSchedule` model that
   has never spoken the old raw columns.
6. `2026_08_05_000003_drop_budget_column_and_enforce_account_not_null` — unaffected by this
   addendum.

This ordering already falls out of the existing filename timestamps (`2026_08_04_*` before
`2026_08_05_*`); no renumbering relative to the budget migrations is needed, only inserting the
two new `2026_08_04_0000{2,3}` files alongside the rewritten `000001`.

## 8. Backend Components to Update

- `app/Services/RecurrenceRuleService.php` — full rewrite of `buildRule()` and every method
  signature per FR-11; `estimatePeriodsBetween()` narrowed to parse `FREQ=`/`INTERVAL=` out of the
  string.
- `app/Models/TransactionSchedule.php` — remove `frequency`/`interval`/`count`/`end_date`/
  `by_day`/`by_month` from casts; add the 6 (+2 for month-end, request-only) `Attribute::make()`
  virtual properties; add the `rrule`-composing `saving()` hook; update all internal
  `RecurrenceRuleService` call sites to the new signature.
- `app/Models/Budget.php` — same treatment, including its `booted()` `hasOccurrenceOnOrAfter()`
  call site and its own `saving()` composer hook.
- `app/Models/Transaction.php::scheduleInstances()` — its direct `buildRule()` call site updates
  to the 2-parameter form.
- `app/Services/BudgetService.php::projectOccurrences()` — its `getRecurrenceBetween()` call site.
- `app/Http/Traits/ValidatesRecurrenceRule.php` — per FR-13/FR-14 (mechanically small: the two new
  month-end rules, and confirming the existing rules still target request fields, not columns).
- `app/Http/Requests/BudgetRequest.php`, `app/Http/Requests/TransactionRequest.php` — no change to
  which fields are validated; confirm `$fillable`-driven mass assignment still lines up with the
  new virtual-attribute names on the model (it does, since the request field names don't change).
- Migrations (FR-15, `transaction_schedules`): rewrite `2026_08_04_000001_...` to only add `rrule`
  nullable; new `2026_08_04_000002_backfill_rrule_on_transaction_schedules_table.php` (real
  backfill, ships to everyone); new
  `2026_08_04_000003_drop_recurrence_columns_from_transaction_schedules_table.php` (guard, drop,
  `NOT NULL`).
- Migration edit (`budgets`, greenfield): rewrite `2026_08_05_000001_create_budgets_table.php` (and
  confirm no other migration in this feature's set references the removed columns) to create the
  final `rrule`-based schema directly — safe in one step, since no installation has ever had this
  table.

## 9. Frontend Components to Update

None beyond what the superseded draft already specified for the two month-end patterns
(`TransactionSchedule.vue`, `BudgetForm.vue`, `Schedule.vue`, `shared/lib/helpers/index.js`,
i18n) — this addendum's storage change is invisible to the frontend by design (Goals). Re-stated
from the superseded draft since it's still required:

- `shared/lib/helpers/index.js` — `by_day`/`by_month` → `rrule.js` options mapper gains
  `bymonthday`/`bysetpos`+`byweekday` branches.
- `TransactionSchedule.vue` — two more `patternMode` options (numeric input for
  days-before-month-end; a plain radio for last-business-day), exclusivity clearing, `by_month`
  pairing extended to all pattern modes.
- `BudgetForm.vue` — extend its flat field list with the 2 new fields.
- `Schedule.vue` — 2 new human-readable summary branches.
- i18n labels for both.

## 10. Testing Requirements

- `RecurrenceRuleServiceTest` — rewritten around the new signature; every existing case
  (ordinal-weekday, `by_month` pairing, bounded-lookahead) re-expressed as an `rrule` string input
  instead of separate parameters; new cases for the two month-end patterns (leap-year/February
  edge cases, weekend-ending-month case) carried over from the superseded draft.
- New round-trip test: setting `frequency`/`interval`/`by_day`/`by_month`/`count`/`end_date` (or
  either month-end field) on a `TransactionSchedule`/`Budget`, saving, reloading from the database,
  and reading each virtual attribute back returns the original values — the core correctness
  property FR-12's accessor/mutator design depends on.
- New test: `$model->rrule` is never mass-assignable (attempting to set it via `fill()`/request
  input has no effect — only the composed `saving()` hook writes it).
- Request-validation feature tests for the two month-end fields' exclusivity/frequency/`by_month`
  rules — unchanged from the superseded draft's plan, since validation targets request fields.
- `scheduleInstances()` regression test — confirm forecast projection is unaffected by the storage
  change for a representative existing pattern (ordinal-weekday) plus both new month-end patterns.
- New migration test for FR-15 (`tests/Feature/Console/TransactionScheduleRruleMigrationTest.php`)
  — seeds rows directly via the query builder in the pre-migration column shape, runs the real
  migration sequence, and asserts the exact resulting `rrule` string per legacy shape (a
  golden-output comparison, not just "it produced *a* string"), plus a case confirming the
  drop-step migration refuses to proceed (throws) if any row's `rrule` is still null/empty.

## 11. Acceptance Criteria

1. `transaction_schedules` and `budgets` each store recurrence as a single `rrule` column;
   `frequency`/`interval`/`count`/`end_date`/`by_day`/`by_month` no longer exist as columns on
   either table.
2. Every existing consumer (`TransactionRequest`, `BudgetRequest`, the Vue pattern picker, the API
   response shape) continues to send/receive the same discrete field names as before this
   addendum — no client-visible contract change.
3. `RecurrenceRuleService`'s public methods take a single `rrule` string instead of 6 separate
   recurrence-shape parameters.
4. Both month-end patterns (days-before-month-end, last-working-day-of-month) work exactly as
   specified in the superseded draft's FR-10/FR-11 semantics, with zero new database columns.
5. A `TransactionSchedule`/`Budget` round-trips every virtual recurrence attribute through save
   and reload without loss.
6. `rrule` is never client-fillable; it is always derived, never accepted directly from a request.
7. `create_budgets_table` produces the final `rrule`-based schema directly (no intermediate
   frequency/by_day/by_month-column state visible to a fresh install) — safe because `budgets` is
   new in this branch and no installation has ever had one.
8. `transaction_schedules`' real, pre-existing `frequency`/`interval`/`count`/`end_date` data
   survives the upgrade intact: the FR-15 add/backfill/guarded-drop migration sequence (Section 7)
   is ordinary, shipping migration history — no separate script, no personal-instance special
   case — and its golden-output test (Section 10) confirms the backfilled `rrule` reproduces each
   legacy column shape exactly.
9. `UPGRADE.md`'s 3.x→4.x section documents this backfill (backup guidance, no downgrade path once
   the old columns are dropped) alongside the existing budget-conversion notes.
10. `vendor/bin/sail artisan test --compact`, `./vendor/bin/pint --dirty`, and
    `./vendor/bin/phpstan analyse` pass.

## 12. Documentation Updates (post-implementation)

- `.ai/docs/assets/transactions/schedules.md` / `.ai/docs/assets/budget/budget.md` — describe the
  `rrule`-backed storage model and both month-end patterns.
- `.ai/docs/features/budget-schedule-redesign/architecture.md` — replace the
  `RecurrenceRuleService` "Tech Stack Notes" bullet's description of `buildRule()`'s parameter list
  with the new single-`rrule` shape; note the virtual-attribute layer on `TransactionSchedule`/
  `Budget`.
- `.ai/docs/features/budget-schedule-redesign/variables.md` — confirm no new config/secrets.

## 13. Release Packaging

Not fully exempt from specification.md Section 12's packaging discipline, unlike the earlier draft
of this section assumed. The `RecurrenceRuleService`/model-layer reshape and the `budgets` schema
edit are internal to this not-yet-released `4.0.0` scope, same as before — nothing to warn
operators about there. But the FR-15 `transaction_schedules` migration sequence is a real,
irreversible-once-run data migration against a table every 3.x installation already has, exactly
like the existing budget-to-`Budget` conversion (`2026_08_05_000002`/`000003`) that `UPGRADE.md`
already documents. It needs the same treatment: a note in `UPGRADE.md`'s 3.x→4.x section
(alongside the budget conversion) covering the backup recommendation and the lack of a downgrade
path once `2026_08_04_000003` drops the old columns. No new pre-upgrade safety-check command is
needed for it — unlike the budget conversion's `app:check:budget-migration`, this backfill has no
"risky data shape" that blocks it; every row's `frequency`/`interval`/`count`/`end_date` combination
that could exist under the current 3.x validation rules is representable as an `rrule` string.

---

## 14. Implementation Task Checklist

Ordered to land safely: reshape the service and models first (so there's a working
`rrule`-backed implementation to test against), then the database changes — the `transaction_schedules`
add/backfill/guarded-drop sequence (FR-15) before the `budgets` table rewrite, since
`2026_08_05_000002`'s transform reads `TransactionSchedule`'s virtual accessors, which only work
once `rrule` is backfilled — then frontend (unaffected in contract, but rebuild/verify), then
tests, then docs.

### Backend — recurrence engine
- [x] `RecurrenceRuleService::buildRule()` — rewrite to `(Carbon $startDate, string $rrule): Rule`
      via `Rule::createFromString()`.
- [x] Rewrite `getRecurrence()`, `hasOccurrenceOnOrAfter()`, `getOccurrencesAfter()`,
      `getRecurrenceBetween()`, `occursOn()` to the single-`rrule`-parameter signature.
- [x] `estimatePeriodsBetween()` — parse `FREQ=`/`INTERVAL=` out of the `rrule` string instead of
      taking them as parameters.

### Backend — models
- [x] `TransactionSchedule.php` — drop old casts; add `Attribute::make()` virtual properties for
      `frequency`/`interval`/`count`/`end_date`/`by_day`/`by_month`/`days_before_month_end`/
      `last_business_day_of_month`; add `saving()` composer hook; update all internal
      `RecurrenceRuleService` call sites.
- [x] `Budget.php` — same treatment, including `booted()`'s `hasOccurrenceOnOrAfter()` call and its
      own `saving()` composer hook.
- [x] `Transaction.php::scheduleInstances()` — update its direct `buildRule()` call.
- [x] `BudgetService.php::projectOccurrences()` — update its `getRecurrenceBetween()` call.

### Backend — validation
- [x] `ValidatesRecurrenceRule` — add `daysBeforeMonthEndRule()`, `lastBusinessDayOfMonthRule()`;
      extend `byDayRule()`'s exclusivity closure both ways; widen `byMonthRule()`'s
      required/prohibited condition to all 3 month-scoped patterns.
- [x] Confirm `BudgetRequest`/`TransactionRequest` need no field-name changes (verification task,
      not new code, per FR-13).

### Database — `transaction_schedules` (FR-15, real backfill)
- [x] `2026_08_04_000001_add_rrule_to_transaction_schedules_table.php` — rewritten (renamed from
      `..._add_by_day_to_...`) to add `rrule` nullable only.
- [x] `2026_08_04_000002_backfill_rrule_on_transaction_schedules_table.php` — new; backfills every
      row from `frequency`/`interval`/`count`/`end_date`(/`by_day`/`by_month` if present).
- [x] `2026_08_04_000003_drop_recurrence_columns_from_transaction_schedules_table.php` — new;
      guards on null/empty `rrule`, drops old columns, makes `rrule` `NOT NULL`.

### Database — `budgets` (greenfield, rewrite in place)
- [x] Rewrite `2026_08_05_000001_create_budgets_table.php` to produce the final `rrule`-based
      schema directly.
- [x] Confirm no other migration in `database/migrations/` references
      `frequency`/`interval`/`count`/`end_date`/`by_day`/`by_month` on either table.

### Database/tooling — `budgets` on the branch author's own instance (FR-16, temporary, non-shipping)
- [x] `app:dev:migrate-budgets-recurrence-to-rrule` Artisan command — no-ops on a fresh install,
      backfills `rrule` from the old columns on the one already-affected instance.
- [ ] Run it against the affected instance; verify output.
- [ ] Drop the old columns on that instance (a local, non-shipping migration or manual DDL —
      author's call).
- [ ] Delete the command and its test from the branch once that instance is confirmed migrated
      and before this branch is proposed for merge toward the release branch.

### Frontend (contract unchanged — verify, extend for month-end patterns only)
- [x] `shared/lib/helpers/index.js` — `bymonthday`/`bysetpos`+`byweekday` branches.
- [x] `TransactionSchedule.vue` — 2 new `patternMode` options + exclusivity/`by_month` wiring.
- [x] `BudgetForm.vue` — extend field list.
- [x] `Schedule.vue` — 2 new human-readable summary branches.
- [x] i18n labels.
- [ ] `vendor/bin/sail npm run dev` rebuild; manual UI check (transaction schedule form + Budget
      modal) confirming no regression from the storage change.

### Tests
- [x] `RecurrenceRuleServiceTest` — rewritten around `rrule`-string input; month-end pattern edge
      cases (leap year/February, weekend-ending month).
- [x] Round-trip test: set virtual attributes → save → reload → read back unchanged.
- [x] Test: `rrule` is not mass-assignable.
- [x] Request-validation feature tests for month-end field exclusivity/frequency/`by_month`.
- [x] `scheduleInstances()` regression test (existing pattern + both new ones).
- [x] Migration golden-output test (FR-15) —
      `tests/Feature/Console/TransactionScheduleRruleMigrationTest.php`, plus the guard-blocks-drop
      case.

### Docs (after implementation lands)
- [x] `.ai/docs/assets/transactions/schedules.md` / `.ai/docs/assets/budget/budget.md`.
- [x] `.ai/docs/features/budget-schedule-redesign/architecture.md`.
- [x] `.ai/docs/features/budget-schedule-redesign/variables.md` (confirm no new variables).
- [x] `UPGRADE.md` — document the `transaction_schedules` recurrence backfill in the 3.x→4.x
      section, alongside the existing budget-conversion notes (Section 13).

### Quality gates
- [ ] `./vendor/bin/pint --dirty`
- [ ] `./vendor/bin/phpstan analyse`
- [ ] `vendor/bin/sail artisan test --compact` (affected suites first, then ask before the full
      suite, per project convention)
