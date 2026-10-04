# Payee profile, settings and matching (Fast Transaction Entry, Phase 3)

As built. Design intent: [specification](../../specifications/fast-transaction-entry/specification.md), Phase 3 and D7–D10, D15. Nothing is recorded automatically in this phase: the profile, matcher and candidates page prepare Phase 4.

## Payee profile

`payee_profiles` (one row per payee, rebuilt at any time) is calculated by `PayeeProfileService::calculate()`.

- **Window:** the 50 newest non-schedule standard withdrawals (payee in `account_to_id`) and deposits (payee in `account_from_id`), ordered by date then ID, no time limit.
- **Dominant category:** the category most single-item transactions of the window are in. `single_item_dominant_count` counts those transactions; `dominant_share` is the share of transactions with any item in that category; `multi_item_share` is the share with more than one item.
- **Wilson bound:** `wilson_lower` = `wilsonLowerBound(single_item_dominant_count, sample_size)`, z = 1.645 (D9). 10/10 → 0.787, 11/11 → 0.803, 20/21 → 0.812, 18/20 → 0.738.
- **Amounts** are on the account side (withdrawal: `amount_from`, deposit: `amount_to`), so they are in the currency of the payee's typical account: median, min, max, `amount_mode_share` (share of the most common amount), `known_amounts` (distinct, most frequent first, capped at 20). Transactions in several currencies are mixed as-is; Phase 4 gate 7 fails on a currency mismatch (spec R3).
- **`typical_account_ids`:** the account side IDs covering at least 20% of the window, most frequent first.

### When it is recalculated

- `RecalculatePayeeProfile` (`ShouldBeUnique` per payee) is dispatched by `ProcessTransactionCreated`, `ProcessTransactionUpdated` (current payee and, from `changedAttributes['config']`, the previous one) and `ProcessTransactionDeleted` (before the config is deleted), through `RecalculatePayeeProfile::dispatchForTransaction()`. Schedules, investments and account-only transfers dispatch nothing.
- `app:payees:recalculate-profiles {userId?}` recalculates every payee; scheduled `dailyAt('02:30')` in the `runs_scheduler` block. It also covers paths that fire no event (schedule recording, reconcile) and is the way to build the first profiles after the upgrade.

## Qualification and candidates

`PayeeProfileService::qualification()` applies the history gate with the user's resolved settings: policy `never` never qualifies, `always` always does, otherwise `sample_size ≥ auto_record_min_history` and `wilson_lower ≥ auto_record_wilson_min`. When it fails, `missing` is the number of further matching (single item, dominant category) transactions that would pass it, or `null` when none ever would.

`GET /api/v1/payees/auto-record-candidates` (`PayeeApiController::getAutoRecordCandidates`, `read`) returns, from the stored profiles, four lists. A payee can be in several. Each row carries the numbers and a `reason`; the profile `currency` is the typical account's.

| Group                  | Rule                                                                                                                                                                                                                  |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `qualifying`           | passes `qualification()`                                                                                                                                                                                              |
| `near`                 | not qualifying, policy not `never`, has a dominant category, and the lower bound is within 0.10 below the minimum, **or** the payee is perfectly consistent but has fewer than `auto_record_min_history` transactions |
| `itemization_mismatch` | at least 5 transactions, and `multi_item_share ≥ 0.3` while _Summary sufficient_, or `≤ 0.05` while _Itemization expected_                                                                                            |
| `template_candidates`  | at least 5 transactions, `amount_mode_share ≥ 0.8`, every transaction single item in the dominant category, and no template with this `payee_id`                                                                      |

The 5-transaction floor of the last two groups is not in the specification; it keeps one-off payees out of them.

## Settings

- `payees.auto_record_policy` (`follow_global` default, `always`, `never`) and `payees.itemization_expected` (default `false`); `Payee::AUTO_RECORD_POLICIES`, validated in `AccountEntityRequest`, persisted by `PayeePersistenceService::CONFIG_FIELDS`.
- `ai_user_settings`: `auto_record_min_history` (10), `auto_record_wilson_min` (0.8), `auto_record_amount_tolerance_percent` (20), `payee_similarity_min` (0.92), `payee_similarity_margin` (0.1). NULL = default, resolved by `AiUserSettingsResolver`. `auto_record_enabled` and `itemization_timeout_days` arrive with Phase 4. The amount tolerance is stored and edited now but only used from Phase 4.

## PayeeMatcher

`PayeeMatcher::match(text, user): ?PayeeMatch` over the user's **active** payees.

- **Normalization** (`PayeeMatcher::normalize()`): `AssetMatchingService::normalizeForMatching()` (now `public static`: NFC, lowercase, transliterated, punctuation to spaces), then digit-only tokens and the legal suffixes `kft zrt bt nyrt kkt ltd gmbh` are dropped. If nothing is left, the base normalization is used. `OMV 4471 BUDAPEST` → `omv budapest`; `Példa Kft.` → `pelda`.
- **Tiers:** `exact` (normalized name or alias line equals the text), `leading_token` (an alias line, as whole leading tokens; the longest wins; names do not take part), `similarity` (best Jaro-Winkler over name and alias lines per payee; returned only above the user's existing `asset_similarity_threshold`). `exact` and `leading_token` are always `autoEligible`; `similarity` is when `score ≥ payee_similarity_min`, the lead over the second best payee (`margin`) is `≥ payee_similarity_margin` and the matched string has at least 4 characters.
- Nothing calls the matcher yet; Phase 4 gate 2 will. The existing AI matching (`AssetMatchingService`) is unchanged.

## Alias uniqueness (D15)

`AccountEntityRequest` rejects, for payees, a name or alias line that normalizes to the same value as another payee's name or alias line (`PayeeMatcher::findConflict()`), and an alias with two lines that normalize equally. Only new or changed values are checked, so legacy collisions never block an unrelated edit. `CsvParserService::loadPayeeLookup()` now maps each alias line.

## Frontend

- `PayeeForm.vue`: _Auto-recording_ and _Itemization_ selects (not in the simplified form, but their values are still sent so a simplified save keeps them); name and alias errors show at their field.
- `PayeeProfileCard.vue` on the payee page; data from `AccountEntityController::payeeProfileData()` through `window.payeeProfile` (`null` until the first calculation).
- Candidates page: `GET /payees/auto-record-candidates` (`payees.auto-record-candidates`), `resources/js/payee/candidates.js`, `AutoRecordCandidates.vue` with four tabs and an edit button opening `PayeeForm`. The "nothing is recorded yet" notice is static until the Phase 4 switch exists. Linked from the payee list (when an AI provider is configured) and from the settings.
- `AiBehaviorSettings.vue`: an _Auto-recording_ section with the five thresholds.
- `AiDocumentViewer.vue`: after a transaction is created from the document, when the chosen payee differs from the one the draft was matched to, it offers _Add "<leading words>" as alias of <payee>_ (leading words of the extracted payee text up to the first word with a digit, at most three) and saves it with one PATCH.

## Tests

`tests/Unit/Services/PayeeMatchingMathTest.php` (Wilson, normalizer), `tests/Feature/FastTransactionEntry/PayeeMatcherTest.php`, `PayeeProfileTest.php`, `AutoRecordCandidatesApiTest.php`, `PayeeSettingsAndAliasTest.php`; `ApiAbilityEnforcementTest` row `payees.auto-record-candidates`.
