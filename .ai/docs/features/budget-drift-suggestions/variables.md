# Variables: Budget drift suggestions

All thresholds are instance-wide, in `config/yaffa.php` under `budget_suggestions` (no env vars, not in the UI). Keys are per granularity `month|quarter|year` unless noted.

| Key                                       | Default                         | Meaning                                                               |
| ----------------------------------------- | ------------------------------- | --------------------------------------------------------------------- |
| `default_sensitivity`                     | `medium`                        | Used when the request gives none or an invalid value                  |
| `default_bias`                            | `neutral`                       | Same for bias (`neutral`, `conservative`, `conservative_rounded`)     |
| `min_history`                             | 6 / 4 / 3                       | Complete periods needed before judging                                |
| `level_window`                            | 12 / 4 / 5                      | Periods averaged into L                                               |
| `horizon`                                 | 12 / 4 / 2                      | Periods averaged into E (cut at `end_date`)                           |
| `cooldown`                                | 3 / 2 / 1                       | Periods after a Budget row starts during which the unit is not judged |
| `sensitivity.{low,medium,high}.tolerance` | 0.20 / 0.10 / 0.05              | Relative \|L − E\| / \|E\| needed                                      |
| `sensitivity.*.persistence`               | e.g. medium month `[3, 4]`      | k of the last n periods must agree with the gap's sign                |
| `materiality_floor_share`                 | 0.0                             | Share of average monthly expenses a gap must exceed; 0 = off          |
| `bias`                                    | factors 0.75 / 1.5, margin 0.03 | Asymmetric tolerance and target margin for conservative modes         |
| `neutral_net_share`                       | 0.25                            | Mixed-sign units netting under this share of gross are neutral        |
| `regularity`                              | `[10,12]` / `[3,4]` / `[2,3]`   | k of n periods with a transaction for a new-budget unit               |

Database: `categories.calculate_budget_suggestions` (boolean, default true).
