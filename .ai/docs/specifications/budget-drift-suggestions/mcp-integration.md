# Budget drift suggestions — MCP integration (future)

Not built. The MCP branch (`feat/mcp_server`) has no budget tools and is unmerged. This file records how the budget suggestions would be exposed once it lands, so the tool stays a thin wrapper.

## `GetBudgetSuggestions` (read tool)

Wraps `BudgetSuggestionService::run()`, the same call as `GET /api/v1/reports/budget-suggestions` and `budget-suggestions:review --json`. No logic of its own.

| Parameter      | Type            | Notes                                                                 |
| -------------- | --------------- | --------------------------------------------------------------------- |
| `category_ids` | integer[]       | Categories to check. Exactly one of `category_ids` or `all` is needed |
| `all`          | boolean         | Review every active, non-excluded category                            |
| `data_through` | string `Y-m`    | Optional. Defaults to the last complete month; later values are clamped |
| `sensitivity`  | `low\|medium\|high`  | Optional, default from `config/yaffa.php`                        |
| `bias`         | `neutral\|conservative` | Optional, default from `config/yaffa.php`                     |

- **Ability:** `read`. The tool writes nothing and must not run any write.
- **Output:** the response contract in `specification.md` ("Response contract"), unchanged: `run`, `units[]`, `series` (`null` for `all`).
- **Amounts** are signed JSON numbers (withdrawals negative). Unit-level amounts are per period in base currency; suggestion and candidate amounts are per occurrence in the row's currency. This differs from the decimal strings of the Eloquent API responses.
- **Impact fields.** Every `suggestion` carries, after `target`, `monthly_impact` (target − E per month, base currency, signed: negative = more spending expected) and `impact_share` (|`monthly_impact`| ÷ `run.average_monthly_expenses`, a fraction, `null` when that is 0). `run` carries `average_monthly_expenses` (base currency, positive) and `materiality_floor_share` (the config echo; 0 = no floor).
- **Ordering.** `units[]` is ordered by the service: `suggestion` units first, by |`monthly_impact`| descending (ties by `category_name`), then all other units by `category_name`. A tool should keep that order and need not re-sort.
- **`suggestion.key`** is stable across identical runs. An agent that monitors on a schedule should keep its own record of keys it has already reported, because the service stores nothing.
- Only `verdict = suggestion` units carry a `suggestion`; other verdicts explain why not (`cooldown`, `insufficient_data`, `irregular`, `missing_rates`, `needs_review`, `no_drift`, `excluded`).

## `ReplaceBudget` (optional write tool)

Would apply a `replace` proposal through `BudgetService::replace` (the old row ends the day before `effective_date`, the new one starts on it) and a `create` proposal through `BudgetService::store`.

- **Ability:** `write`. It must reuse the existing budget store/replace validation, not bypass it.
- **Input:** `budget_id` (the chosen candidate; null for `create`), `effective_date`, `amount` (per occurrence, in the row's currency, as in the suggestion/candidate), and `category_id` for `create`.
- The agent should show the proposal to the user and apply it only on the user's approval: the product intent is that the user judges and decides every change.
- Stopping monitoring for a category is a category update (`calculate_budget_suggestions` = false), not a budget write.
