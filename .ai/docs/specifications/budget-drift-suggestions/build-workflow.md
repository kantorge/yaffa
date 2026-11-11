# Budget drift suggestions — build workflow

How the MVP in `specification.md` is built by a sequence of agents, each with a fresh context. The repo carries the context between agents; no agent relies on another agent's conversation.

## Inputs every agent reads first

1. `CLAUDE.md` and the role files in `.ai/agents/` named for its phase (table below).
2. `.ai/docs/specifications/budget-drift-suggestions/specification.md`, including its decisions log. It is the source of truth for intent during the build.
3. The handoff notes of all earlier phases in this folder (`phase-*-handoff.md`).

If the spec and the code disagree on how existing code behaves, the code wins: record the discrepancy in the decisions log and tell the owner.

## Branches

- Integration branch: `feat/budget-suggestions`, created from `develop`.
- One branch per phase: `feat/budget-suggestions-phase-<id>`, created from the integration branch after the previous phase has merged.
- Each phase ends with a PR into the integration branch. The owner merges it. The integration branch goes to `develop` once the MVP is done.

## Phases and agents

| Id | Scope | Role files | Done when |
| --- | --- | --- | --- |
| 1 | Extract `BudgetChartService` from `ReportApiController::budgetChart()`; add batched per-user aggregation; measure "Review all" runtime | `laravel-backend`, `testing` | `budgetChart` regression test passes with byte-identical output; runtime measured on a realistic dataset and recorded in the handoff |
| 2a | `BudgetSuggestionService` (units, verdicts, drift, new budgets, amount conversion, recommended series); `budget_suggestions` config block; `categories.budget_suggestions_excluded` migration and model cast | `laravel-backend`, `testing` | All detector, scope, cutoff and new-budget unit tests from the spec pass |
| 2b | GET endpoint with Form Request and `abilities:read`; `ApiAbilityEnforcementTest` row; `budget-suggestions:review` command with `--json` and `--backtest`; contract tests; `mcp-integration.md` | `laravel-backend`, `testing` | Feature, ability and contract tests pass; the command's output matches the endpoint's for the same input |
| 3 | Report page: run controls, both run buttons, recommendation list (create, hide, stop monitoring), unit verdict list, recommended-budget series, category form toggle, moving-average fix | `frontend`, `testing` | Assets build; the owner's UI check passes |
| 3t | Translations for every new string in all `/lang` files | `frontend` | Every locale file has the keys; non-English strings flagged for native-speaker review in the PR |
| D | As-built feature docs in `.ai/docs/features/budget-drift-suggestions/` | `documentation` | Docs extracted from the merged code, not copied from the spec |

Phases run strictly in order. An agent never starts the next phase.

## The loop for every phase

1. **Read** the inputs above.
2. **Plan.** Write `phase-<id>-plan.md` in this folder: the files to touch, the approach, the tests to add, and any spec point that is unclear. Stop and wait for the owner's approval. Keep it under one page.
3. **Build** on the phase branch, following the approved plan. Follow `CLAUDE.md`: prefix commands with `vendor/bin/sail`, write new tests in Pest, run `vendor/bin/sail bin pint --dirty`, and run the minimum affected tests (`vendor/bin/sail artisan test --compact --filter=...`).
4. **Verify** against the phase's "done when" and the spec's verification list for that phase. Ask the owner before running the full suite.
5. **Hand off.** Write `phase-<id>-handoff.md` using the template below. Append deviations to the spec's decisions log.
6. **Independent review.** A separate agent that did not build the phase reviews the diff against the spec, the plan and the handoff (prompt below). The builder fixes confirmed findings.
7. **Owner gate.** Open the PR into the integration branch. The owner reviews and merges.

## Guardrails for all agents

- No database changes beyond `categories.budget_suggestions_excluded`. No new tables, no `users` columns.
- The GET endpoint and the service write nothing. A contract test enforces this.
- `ReportApiController::budgetChart()` keeps its response exactly as before.
- Every new or changed API action declares its ability middleware, and `ApiAbilityEnforcementTest` gets a matching row (see `CLAUDE.md`).
- Do not relax required properties on the `Transaction` model or its relations. Test fixtures create complete transactions.
- Thresholds live only in `config/yaffa.php` under `budget_suggestions`. No hard-coded numbers in the service.
- Out-of-scope items in the spec (scheduled monitoring, stored suggestions, seasonality, inflation-rate suggestions) are not built, even partly.

## Owner checkpoints

| After | What the owner does |
| --- | --- |
| 1 | Reads the measured runtime and decides whether the queued fallback is needed. The decision goes into the decisions log before phase 2a starts |
| 2b (the command exists from here) | Runs `budget-suggestions:review {user} --backtest` against a copy of real data, tunes `config/yaffa.php`, and records the final values in the decisions log |
| 3 | Rebuilds assets with `vendor/bin/sail npm run dev` and checks the report page, including "Review all" and the recommended series |

## Handoff note template

```markdown
# Phase <id> handoff

## Built
- <what exists now, by file or class>

## Interfaces for the next phase
- <public methods, signatures, return shapes, config keys, routes>

## Deviations from the spec
- <what changed and why; also appended to the decisions log>

## Measurements
- <runtime, dataset size, query counts; phase 1 must fill this>

## Tests
- <added test files; command to run them; current result>

## Known issues and open questions
- <anything the next agent or the owner must know>
```

Keep it to one or two pages. The next agent reads this instead of the previous conversation.

## Reviewer prompt

> You review phase <id> of the budget drift suggestions MVP. You did not build it. Read `CLAUDE.md`, `.ai/docs/specifications/budget-drift-suggestions/specification.md`, `phase-<id>-plan.md` and `phase-<id>-handoff.md`, then the diff of branch `feat/budget-suggestions-phase-<id>` against `feat/budget-suggestions`. Report, most severe first: behaviour that contradicts the spec, guardrail violations, missing tests from the spec's verification list, and deviations not recorded in the decisions log. For each finding give the file, the line and a concrete failing scenario. Do not report style issues that Pint fixes. Do not change code.

## Orchestrator prompt (optional)

Use this to run the whole build from one session that holds only the spec and the handoffs.

> You orchestrate the build of the budget drift suggestions MVP. Follow `.ai/docs/specifications/budget-drift-suggestions/build-workflow.md` exactly. For each phase in order (1, 2a, 2b, 3, 3t, D), start a fresh agent with only the inputs the workflow lists. Stop and ask me at every plan approval, every owner checkpoint and every PR. After each phase, read only its handoff note and the reviewer's findings; do not pull code into your own context. Never start a phase before the previous one is merged.
