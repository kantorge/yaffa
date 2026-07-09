# QIF/CSV Import — Test Coverage

Derived from `architecture.md`, `flows.md`, `permissions.md`, `variables.md` (reverse-engineered from `feat/qif-csv-import`) plus the surviving finding from the prior security audit (no rate limiting on `/imports/parse` / `/imports/file-profiles/suggest`). Only load-bearing, deterministic rules are scored — the ones where getting it wrong crosses an authz, data, money, or privacy boundary. Cosmetic/UI-only behavior (tooltips, disabled buttons, wizard pre-fill) is out of scope.

Status is only ever **existing** when a test in the repo asserts it today. A rule that is merely a good idea is **proposed**. A rule with no test and, in one case, no way to add one yet is **none**.

## Coverage Map

| # | Use case | Rule (doc) | Expected behavior (+ deny case) | Evidence (doc + code) | Type | Status |
|---|---|---|---|---|---|---|
| 1 | Parse — account ownership | `ImportPolicy::parse` requires `user_id` match (`permissions.md` § `ImportApiController::parse`) | Allow: own account. Deny: another user's account → 403 | `flows.md` a.3; `app/Policies/ImportPolicy.php:10` | integration | existing |
| 2 | Parse — target must be an account, not a payee | `ImportPolicy::parse` also requires `isAccount()` | Deny: own `AccountEntity` with `config_type=payee` → 403 | `flows.md` a.3; `permissions.md` § `ImportApiController::parse` | integration | none |
| 3 | Parse — profile view authz | `FileImportProfilePolicy::view` = `isSystem() \|\| isUserOwnedBy` | Allow: system profile, own profile. Deny: another user's `type=user` profile → rejected | `flows.md` a.4; `app/Policies/FileImportProfilePolicy.php:15` | integration | existing (see caveat) |
| 4 | Parse — profile `file_type` must match `source_type` | `ImportParseRequest` closure compares `$profile->file_type` to `source_type` | Deny: CSV-type profile supplied for a QIF parse, or vice versa | `flows.md` a.2, b.1; `app/Http/Requests/ImportParseRequest.php:63-65` | integration | proposed |
| 5 | Parse — no profile resolves | Fail-closed: no explicit ID and no account default → `422 CSV_PROFILE_REQUIRED`, no parsing attempted | Deny: CSV parse with no profile and no account default | `flows.md` a.5 | integration | existing |
| 6 | Parse — row cap enforcement | `IMPORT_MAX_ROWS` throws `RuntimeException` once exceeded, both CSV and QIF | Deny: file exceeding cap → `422 IMPORT_PARSE_FAILED` (CSV) / `RuntimeException` (QIF unit level) | `flows.md` a.6, b.2; `variables.md`; `CsvParserService.php:54`, `QifParserService.php:151,221` | integration + unit | existing |
| 7 | Parse/Suggest — file size cap enforcement | `IMPORT_MAX_FILE_SIZE_MB` caps `File::max()` on both upload endpoints | Deny: upload above the configured MB cap → 422 validation error | `variables.md`; `ImportParseRequest.php:15,68`; `SuggestFileImportProfileRequest.php:12` | integration | none |
| 8 | Parse — payee fuzzy-match enrichment, owner-scoped | `enrichDraftsWithPayeeMatches` reads only `$user->payees()` | Draft gets `matched_payee` from the caller's own payees; another user's identically-named payee never matches | `flows.md` a.7; `permissions.md` § Enrichment Read Scoping; `ImportNormalizationService.php:92-94` | unit | none |
| 9a | Parse — duplicate-candidate scoring and bounding | `ImportDuplicateDetectionService::enrichDrafts` scores/bounds candidates (≤10) within a date window | Matching transaction surfaces as a candidate with a score; result set is capped | `flows.md` a.8; `ImportDuplicateDetectionService.php:119` | integration | existing |
| 9b | Parse — duplicate-candidate scoring, cross-user isolation | Same service reads only `$user->transactions()` | Another user's transaction with identical date/amount never appears as a candidate | `permissions.md` § Enrichment Read Scoping | integration | none |
| 9c | Parse — scheduled-transaction candidate scoring and bounding | `ImportDuplicateDetectionService::enrichDraftsWithScheduleCandidates` scores/bounds candidates (≤10) within a `next_date` window, same engine/settings as 9a | Matching schedule-owning transaction surfaces as a `schedule_candidates` entry keyed on `next_date` (not `date`); result set is capped | `flows.md` a.9, f; `ImportDuplicateDetectionService.php` (`enrichDraftsWithScheduleCandidates`, `loadScheduleWindow`) | integration | existing |
| 9d | Parse — scheduled-transaction candidates exclude inactive schedules and non-schedule transactions | Query requires `schedule = true` and `transactionSchedule.active = true` | An inactive schedule, or a plain (non-schedule) transaction matching on date/amount, never appears in `schedule_candidates` | `architecture.md` § Known Risks; `ImportDuplicateDetectionService::loadScheduleWindow()` | integration | existing |
| 9e | Parse — scheduled-transaction candidates, cross-user isolation | Same `$user->transactions()` owner-scoped relation as 9b | Another user's matching active schedule never appears as a candidate | `permissions.md` § Enrichment Read Scoping | integration | existing |
| 10 | Parse — related-AI-document enrichment, scoped + bounded | `enrichDraftsWithRelatedAiDocuments` reads only `AiDocument::where('user_id', $user->id)`, bounded by time window and result count | Best match ranked first by confidence; old/excess/foreign-user documents excluded | `flows.md` a.9; `permissions.md` § Enrichment Read Scoping; `ImportNormalizationService.php:305` | unit | existing |
| 11 | Profile create — server-controlled ownership fields | `user_id`/`key`/`type` are `prohibited` in the request; controller hard-sets `type='user'`, `user_id=$user->id` regardless of what else the client sends | Deny/ignore: client-submitted `type=system` or `user_id=<other>` in the create/update body never takes effect | `flows.md` c (Create); `permissions.md` Create row; `FileImportProfileRequest.php:36-38`; `FileImportProfileApiController.php:75-76` | integration | proposed |
| 12a | Profile update — foreign profile denied | `FileImportProfilePolicy::update` = `isUserOwnedBy` | Deny: PATCH another user's `type=user` profile → 403 | `flows.md` c (Update); `permissions.md` Update row | integration | existing |
| 12b | Profile update — system profile immutable | Same policy; `null`-owner system row can never satisfy `isUserOwnedBy` | Deny: PATCH a `type=system` profile → 403 | `flows.md` c (Update); `permissions.md` Update row + Summary | integration | proposed |
| 13a | Profile delete — system profile denied | `FileImportProfilePolicy::delete` = `isUserOwnedBy` | Deny: DELETE a `type=system` profile → 403 | `flows.md` c (Delete); `permissions.md` Delete row | integration | existing |
| 13b | Profile delete — foreign profile denied | Same policy | Deny: DELETE another user's `type=user` profile → 403 | `flows.md` c (Delete); `permissions.md` Delete row | integration | proposed |
| 13c | Profile delete — referenced by an account | Controller checks `accountEntities()->exists()` before deleting | Deny: profile is a `preferred_file_import_profile_id` for ≥1 account → 422, row survives | `flows.md` c (Delete); `FileImportProfileApiController.php:96-100` | integration | existing |
| 14 | Profile create/update — `mapping_json` canonical-field validation | `mapping_json.*` must be a known `ImportCanonicalField` value | Deny: mapping a column to an unknown/made-up field name → 422 | `architecture.md` (implicit via `FileImportProfileRequest.php:56`) | integration | none |
| 15 | Profile create/update — prohibited executable keys | `options_json.matching_rules`/`actions`/`defaults`/`transform_catalog` are `prohibited` for user-submitted profiles (only the system registry can populate them) | Deny: client-submitted `matching_rules` or `actions` in create or update → 422 | `architecture.md` § Known Risks (ReDoS note); `FileImportProfileRequest.php:59-61` | integration | existing |
| 16 | Profile create/update — single-character delimiter | `delimiter` capped at `max:1` | Deny: multi-character delimiter on create and on update → 422 | `FileImportProfileRequest.php:44` | integration | existing |
| 17 | Profile list — cross-user exclusion | `selectableForUser` scope backs `index`; `viewAny` is a coarse gate only | Another user's `type=user` profile never appears in `data` for a different caller | `permissions.md` List row; `FileImportProfile.php:79` | integration | none |
| 17b | Profile list — accountEntities eager-load scoped per profile | `index`'s eager load filters `accountEntities` to `where('user_id', $user->id)` even for a shared system profile | Caller only ever sees their own account names against a profile, never another user's | `permissions.md` § `AccountEntity.preferred_file_import_profile_id` Read row | integration | existing |
| 18 | Account preferred-profile FK validation | `AccountEntityRequest` closure requires the value to resolve via `selectableForUser` | Allow: own/system profile. Deny: another user's profile → session validation error | `permissions.md` § `AccountEntity.preferred_file_import_profile_id`; `AccountEntityRequest.php:42-60` | integration | existing |
| 19 | AI suggestion — fail-closed with no provider config | Controller looks up `AiProviderConfig` strictly by `user_id=$user->id`; no fallback/shared key | Deny: no config saved → 422, directs user to configure a provider | `flows.md` d.3; `FileImportProfileApiController::suggest` line 111 | integration | existing |
| 20 | AI suggestion — optional `account_id` authz | If given, must be viewable by caller (`Gate::authorize('view', $account)`); code does not `abort` on failure, it silently skips context | Own account_id: context added. Foreign/inaccessible account_id: request is *not* rejected — behavior needs confirming as intentional | `flows.md` d.4; `permissions.md` § AI profile suggestion | integration | none |
| 21 | AI suggestion — sample trimming and response sanitization | Trims to header + first 10 data rows; strips any AI-suggested field name that isn't a known `ImportCanonicalField` | Long CSV trimmed to 10 rows; unknown field name converted to a `confidence_notes` entry, never accepted as a mapping | `flows.md` d.5,7; `AiImportProfileSuggestionService.php:22,172` | unit | existing |
| 22 | AI suggestion — provider failure, no detail leak | `AiProviderFailureException` → `502` with a hardcoded generic message; controller never echoes `$e->getMessage()` | Deny: provider error → 502, response body contains only the generic string, not the raw provider error | `flows.md` d.8; `FileImportProfileApiController.php:145-148` | integration | existing |
| 23 | AI suggestion / parse — unauthenticated | `auth:sanctum` + `verified` middleware on both controllers | Deny: no session → 401 | `permissions.md` § Enforcement Model | integration | existing |
| 24 | Finalize — reuses `store-standard`, single transaction | No import-specific write path; draft finalize dispatches to the pre-existing endpoint | Exactly one `Transaction` (+ items) row created per successful finalize | `flows.md` e.1-4 | integration | existing |
| 25 | Finalize — no server-side idempotency guard for plain drafts | Spec explicitly delegates double-submit prevention to the frontend; no dedup key exists for a draft without an `ai_document_id` | Two rapid finalize calls for the *same* CSV/QIF draft (no `ai_document_id`) currently create two `Transaction` rows — this is accepted/known, not fixed | `architecture.md` § Known Risks; `flows.md` e.5 | integration | none (no test possible for a fix; a test pinning *current* behavior is proposed) |
| 26 | Finalize — `ai_document_id` double-submit protection (inherited) | `store-standard`'s own guard rejects a second finalize referencing the same already-finalized `AiDocument` | Deny: repeat submit with same `ai_document_id` → 422, only one transaction exists | `flows.md` e.4 (out of this feature's code, but exercised via import's finalize path) | integration | existing |
| 27 | System profile sync — deploy-time upsert | `SyncSystemFileImportProfilesCommand` upserts by `key`, force-sets `user_id=null`/`type=system`, runs on every container start, idempotent | Repeated runs converge to the same row count/content; no authz check needed (trusted, code-only input) | `flows.md` f; `SyncSystemFileImportProfilesCommand.php:20-37` | integration | existing |
| 28 | Rate limiting on `/imports/parse` and `/imports/file-profiles/suggest` | No `throttle` middleware anywhere in `routes/api.php` or `bootstrap/app.php`'s `api` group | An authenticated user can call either endpoint (file parsing up to `IMPORT_MAX_ROWS`, or a paid outbound AI call) unlimited times per minute | `architecture.md` § Known Risks; `variables.md` § Pre-Go-Live Checklist; `routes/api.php:136-143`; `bootstrap/app.php:21-39` | — | none (gap; not testable until throttle middleware is added) |

## Existing coverage

**Parse authz & profile resolution**
- Own-account allow / foreign-account deny → `ImportApiParseTest::test_qif_parse_valid_returns_runtime_draft_payload`, `::test_qif_parse_forbidden_for_non_owned_account`
- System-profile view allow → `::test_csv_parse_valid_with_system_profile`
- Own-profile view allow → `::test_csv_parse_valid_with_user_profile_mapping_only`
- Foreign-profile deny (rows #3) → `::test_csv_parse_returns_422_for_foreign_owned_profile_id` — **caveat**: this only proves the end-to-end deny; because `ImportParseRequest`'s validation closure and the controller's `Gate::authorize('view', $profile)` both reject the same input, the test cannot tell you which layer is actually catching it. If the form-request closure were ever weakened, this test would not reveal that the controller-level Gate is carrying the defense alone.
- Account preferred-profile fallback used when no explicit ID → `::test_csv_parse_uses_account_preferred_profile_when_none_selected`
- `CSV_PROFILE_REQUIRED` fail-closed → `::test_csv_parse_returns_422_when_no_profile_and_no_account_default`
- Row-cap → `422 IMPORT_PARSE_FAILED` → `::test_csv_parse_returns_structured_422_for_structural_errors`; QIF row-cap unit-level → `QifParserServiceTest::test_parse_content_throws_runtime_exception_when_entry_count_exceeds_max_rows`
- Nonexistent profile ID → `422` → `ImportApiParseTest::test_csv_parse_returns_422_for_nonexistent_profile_id`
- Partial-success payload (mixed valid/invalid rows) → `::test_csv_parse_returns_partial_success_payload_for_mixed_valid_and_invalid_rows`

**Duplicate detection**
- Candidate returned with score/summary shape → `ImportDuplicateDetectionTest::test_csv_parse_enriches_drafts_with_duplicate_candidates_and_similarity_scores`
- Candidate list bounded to 10 → `::test_csv_duplicate_candidates_are_bounded`

**Scheduled-transaction matching**
- Candidate returned with score/summary shape (`next_date`, `frequency`) → `ImportScheduleSimilarityTest::test_csv_parse_enriches_drafts_with_schedule_candidates_and_similarity_scores`
- Candidate list bounded to 10 → `::test_csv_schedule_candidates_are_bounded`
- Inactive schedule excluded → `::test_inactive_schedule_is_excluded_from_candidates`
- Non-schedule transaction excluded (and still surfaces as a regular duplicate candidate) → `::test_non_schedule_transaction_does_not_appear_as_schedule_candidate`
- Cross-user isolation → `::test_schedule_candidates_are_isolated_per_user`

**Related AI documents**
- Ranking by confidence, `matched_on` signals, and implicit cross-user exclusion (a document without `->for($user)` is excluded from the 2-item result) → `ImportNormalizationServiceTest::test_enrichs_drafts_with_related_ai_document_candidates_using_matching_signals`
- Time-window and result-count bounding (old document excluded, capped at 3) → `::test_related_ai_document_matching_is_bounded_by_time_window_and_result_count`

**Profile CRUD**
- Full own-profile CRUD lifecycle → `ImportAuthorizationTest::test_user_can_crud_own_profiles`
- Foreign-profile update deny → `::test_user_cannot_edit_another_users_profile`
- System-profile delete deny → `::test_user_cannot_delete_system_profiles`
- Delete blocked while referenced by an account → `::test_cannot_delete_profile_in_use_by_an_account`
- `options_json` unknown-key rejection (create + update) → `::test_store_rejects_unknown_options_json_keys`, `::test_update_rejects_unknown_options_json_keys`
- `options_json` valid-key acceptance → `::test_store_accepts_valid_options_json_keys`
- `matching_rules`/`actions` rejected on create and update → `ImportApiParseTest::test_profile_create_and_update_reject_forbidden_executable_keys`
- Multi-character delimiter rejected on create and update → `::test_profile_store_rejects_multi_char_delimiter`, `::test_profile_update_rejects_multi_char_delimiter`
- `accountEntities` eager-load scoped to caller → `ImportAuthorizationTest::test_index_includes_account_entities_for_current_user`
- Unauthenticated CRUD requests denied → `::test_unauthorized_requests_to_import_profile_routes_are_denied`

**Account preferred-profile FK**
- Own/system allow, foreign deny → `ImportAuthorizationTest::test_user_can_update_account_preferred_profile_only_with_accessible_profile`

**AI suggestion**
- Unauthenticated → 401 → `ImportProfileSuggestTest::test_unauthenticated_request_is_rejected`
- No provider config → 422 → `::test_returns_422_when_no_ai_provider_is_configured`
- Happy path shape → `::test_returns_suggestion_for_authenticated_user_with_ai_provider`
- File content actually forwarded to the service → `::test_service_is_called_with_csv_content_from_uploaded_file`
- Unparseable upload → 422 → `::test_returns_422_when_uploaded_file_is_not_parseable_csv`
- Provider failure → 502, generic message → `::test_returns_502_when_ai_provider_call_fails`
- Own `account_id` passed through as context → `::test_optional_account_id_is_passed_to_suggestion_service`
- Missing file → 422 validation error → `::test_returns_422_when_file_field_is_missing`
- Sample trimming to header + 10 rows, quoted-field handling → `AiImportProfileSuggestionServiceTest::test_trim_csv_returns_header_row_and_up_to_ten_data_rows`, `::test_trim_csv_with_fewer_than_ten_rows_returns_all`, `::test_trim_csv_properly_escapes_quoted_fields`
- Empty content rejected → `::test_trim_csv_throws_for_empty_content`
- Unknown AI-suggested field names stripped into `confidence_notes`, valid ones kept → `::test_sanitize_response_strips_unknown_canonical_field_names`, `::test_sanitize_response_keeps_all_valid_canonical_field_names`, `::test_sanitize_response_preserves_existing_confidence_notes`

**Finalize (existing `store-standard` endpoint, exercised from the import flow)**
- Exactly one transaction created with normalized values → `ImportTransactionCreateFromDraftTest::test_finalize_csv_draft_creates_exactly_one_transaction_with_normalized_values`
- Draft with warnings still finalizes → `::test_draft_with_warnings_can_still_finalize`
- Failed finalize creates nothing, explicit retry succeeds → `::test_failed_finalization_does_not_create_transaction_and_requires_explicit_retry`
- Repeat submit with same `ai_document_id` rejected, only one transaction survives → `::test_repeated_finalize_with_same_ai_document_does_not_create_duplicate_transaction`

**Parsers (unit)**
- CSV: system-profile mapping/rules, Windows-1252→UTF-8 conversion, unmatched-row handling, single payee-lookup query regardless of row count, sanitized exception messages → `CsvParserServiceTest` (6 tests)
- QIF: entry parsing + split warning, unsupported-section skip, missing-terminator recovery, non-UTF8 conversion, row-cap `RuntimeException` → `QifParserServiceTest` (5 tests)
- QIF→draft normalization, invalid-entry `failed_validation` status → `ImportNormalizationServiceTest::test_normalizes_qif_entries_into_draft_payloads`, `::test_marks_invalid_entry_as_failed_validation_and_keeps_warnings`

**Deploy-time sync**
- Registry structure (4 `matching_rules` on the first system profile) → `SyncSystemFileImportProfilesCommandTest::test_registry_contains_expected_system_profile_structure`
- Idempotent upsert, no duplicate rows across repeated runs → `::test_sync_command_is_idempotent_and_loads_profiles`

## Proposed tests

**Parse flow (`ImportApiParseTest`)**
- `test_qif_parse_forbidden_for_own_payee_entity` — assert: `import.parse` on a `config_type=payee` `AccountEntity` owned by the caller → 403. Negative case: same request with a `config_type=account` entity → 200. Type: integration.
- `test_csv_parse_rejects_qif_profile_for_csv_source_type` (and the reverse) — assert: `file_import_profile_id` pointing at a `file_type=qif` profile with `source_type=csv` → 422 (or vice versa). Negative case: matching file_type/source_type pair → 200. Type: integration.
- `test_parse_rejects_file_over_configured_size_cap` — arrange: `Config::set('yaffa.import_max_file_size_mb', 1)`, upload a >1MB fake file. Assert: 422 validation error on `file`. Negative case: file under the cap → 200. Type: integration.
- `test_mapping_json_rejects_unknown_canonical_field` — POST `/imports/file-profiles` with `mapping_json => ['Col' => 'not_a_real_field']`. Assert: 422, `assertJsonValidationErrors(['mapping_json.Col'])`. Negative case: a real `ImportCanonicalField` value → 201. Type: integration.
- `test_store_ignores_client_submitted_user_id_and_type` — POST with `user_id => $otherUser->id, 'type' => 'system'` alongside valid fields. Assert: either 422 (prohibited-field validation fires) or, if it passes some other way, the persisted row still has `type='user'` and `user_id=$actingUser->id`. Negative case: omitting those fields succeeds identically. Type: integration. This is the regression test for the security audit's "mass-assignment on FileImportProfile" finding — currently verified only by reading the code, not by a test.
- `test_user_cannot_patch_system_profile` — PATCH a `type=system` profile with a caller who owns no profiles. Assert: 403. Type: integration.
- `test_user_cannot_delete_another_users_profile` — DELETE a `type=user` profile owned by a different user. Assert: 403, row survives. Type: integration.
- `test_index_excludes_other_users_profiles` — create a `type=user` profile for `$otherUser`; assert it is absent from `data` in `$user`'s `GET /imports/file-profiles` response. Type: integration.

**Enrichment (`ImportNormalizationServiceTest` / new `ImportApiParseTest` case)**
- `test_enrich_drafts_with_payee_matches_returns_owner_scoped_match` — arrange: caller has a payee named "Coffee Shop"; call `enrichDraftsWithPayeeMatches` directly. Assert: `matched_payee.id` matches the owned payee. Type: unit.
- `test_enrich_drafts_with_payee_matches_ignores_other_users_payees` — arrange: only a *different* user has a payee with that name. Assert: `matched_payee` is `null`. Negative case: same setup but payee belongs to the caller → matched. Type: unit.
- `test_duplicate_candidates_never_include_another_users_transaction` — arrange: another user has an identical-date/amount transaction; caller has none. Assert: `duplicate_candidates` is empty for the draft. Type: integration.

**AI suggestion (`ImportProfileSuggestTest`)**
- `test_foreign_account_id_is_silently_ignored_not_rejected` (or, if the intended behavior is actually to reject — flip the assertion once confirmed with the team) — pass `account_id` belonging to another user. Assert current behavior explicitly (200 with no account context vs. 403) so this stops being an open question the next time someone touches this code path. Type: integration.
- `test_provider_failure_response_never_contains_raw_exception_message` — throw `AiProviderFailureException` with a distinctive message (e.g. containing an API key fragment or stack detail); assert the JSON response body does **not** contain that string. Type: integration. Strengthens existing case #22 beyond "contains a generic phrase."

**Finalize**
- `test_repeated_finalize_of_plain_csv_draft_currently_creates_two_transactions` — finalize the same CSV-sourced draft (no `ai_document_id`) twice in a row via `store-standard`. Assert: `assertDatabaseCount('transactions', 2)` — pins the *documented, accepted* current gap so a future accidental fix or regression is visible either way. Type: integration.

## Recommended CI gate

This repo already runs `.github/workflows/test-unit.yml` and `test-feature.yml` on every PR (path-filtered on `app/**`, `tests/Unit|Feature/**`, `routes/**`, migrations/factories), via `vendor/bin/phpunit --testsuite Unit` / `--testsuite Feature` — no Pest, matching `CLAUDE.md`. All rows in this map marked `unit` or `integration` belong in those two existing suites; no new workflow is needed, only new test files under `tests/Unit/Services/Import/` and `tests/Feature/API/V1/` (or an extension of `ImportAuthorizationTest.php`).

- **Every PR (already wired, keep required)**: `test-unit.yml` + `test-feature.yml` — add the proposed cases above as new `test_*` methods in the existing files; they run under the current path filters with no workflow changes.
- **Guarded-live (do not add to default CI)**: none of this feature's rules require one — `AiImportProfileSuggestionService` is exercised entirely through Prism-facing unit tests and mocked-service Feature tests (`$this->mock(AiImportProfileSuggestionService::class)`), never a real provider call. If a true end-to-end "hits a real LLM" smoke test is ever wanted, gate it behind `workflow_dispatch` only, never `pull_request`.
- **Manual / reviewer checklist item** (not automatable via PHPUnit): XSS-safety of Vue-rendered draft fields (`payee`, `memo`, `comment` from user-uploaded files rendered in `ImportDraftTable.vue`/`DuplicateCandidatesPanel.vue`) — no Dusk or JS test exists for this feature; add to a manual pre-release checklist unless a Dusk test is scoped separately.
- **Branch protection**: mark `Run Unit Tests` and `Run Feature Tests` as required status checks on the `develop` branch (this repo's actual integration branch per `git status`), so a red run blocks merge. This is a suggestion for the user to apply in repo settings — not applied here.

## Gaps — documented but unverified

Ranked by what crossing them exposes.

1. **No rate limiting on `/imports/parse` or `/imports/file-profiles/suggest`** (row #28). The one surviving finding from the prior security audit. Both endpoints are expensive by design — one runs a parser up to `IMPORT_MAX_ROWS`, the other makes a paid outbound AI call using the user's own key — and neither has a `throttle` middleware anywhere in `routes/api.php` or the `api` group in `bootstrap/app.php`. A compromised or malicious authenticated account can drive unbounded parsing CPU or unbounded AI spend today. **Cannot be tested until the throttle middleware is added** — this is a fix-then-test item, not a test-now item. Ranked first because it's the only finding the security audit left open, and it's a live cost/abuse vector, not a theoretical one.
2. **`enrichDraftsWithPayeeMatches` has zero test coverage** (row #8). This is a data-enrichment path that reads another table (`$user->payees()`) scoped by ownership, exactly the kind of "someone changes `$user->transactions()` to `Transaction::query()`" regression `permissions.md` warns would go uncaught by a Policy test — and here there isn't even a Feature test asserting the resulting `matched_payee` field's shape or scoping. Ranked second because it's a live, silent cross-user-exposure risk in the same family as the isolation rules that already have tests (duplicate detection, related documents) — this one is the one channel that was missed.
3. **Mass-assignment protection on `FileImportProfile` create/update is unverified by test** (row #11). The security audit confirmed `user_id`/`key`/`type` are `prohibited` and the controller hard-sets them — correct in code — but no test sends `user_id`/`type` in a create/update payload and asserts the override is rejected or ignored. A future refactor of `FileImportProfileRequest` or the controller's hard-set lines could silently reopen this and nothing would fail.
4. **Cross-user isolation for duplicate-detection candidates is unverified** (row #9b). Bounding and scoring are tested; a foreign user's matching transaction never appearing as a candidate is not. Same class of risk as #2, lower severity because duplicate candidates only affect UI hinting, not enrichment data returned wholesale.
5. **System-profile update immutability (403) and foreign-profile delete (403) lack direct tests** (rows #12b, #13b). Both are covered by the same trivial `isUserOwnedBy` boolean, and the *delete* side of system-profile denial is tested, so the residual risk is low — but a rename or logic split of the policy method would not be caught for the untested branches.
6. **Profile list (`index`) cross-user exclusion is unverified** (row #17). `selectableForUser` is exercised indirectly (via the parse-time deny test), but no test asserts the `index` endpoint itself never returns another user's `type=user` profile in `data`.
7. **AI-suggestion `account_id` cross-user behavior is undocumented-as-tested and flagged as open in `flows.md` itself** (row #20): the code silently skips context on an inaccessible account rather than rejecting the request, and nobody has confirmed this is intentional. Low severity (the account is only used for prompt text, never written to), but it's a "flagged, not confirmed" item per the docs and should get a test that pins whichever behavior is decided correct.
8. **No test enforces the file-size cap** (row #7) despite `IMPORT_MAX_FILE_SIZE_MB` being a real, working config value on both upload endpoints. Low severity on its own (Laravel's `File::max()` rule is a well-trodden path), but combined with gap #1 (no rate limit), an untested cap is one refactor away from silently becoming a no-op.
9. **Finalize double-submit for plain (non-AI-document) drafts has no server-side guard and no test pinning current behavior** (row #25). This is a known, accepted risk per `SPECIFICATION.md` (delegated to the frontend), not a bug to fix here — but with zero test coverage, nobody would notice if this got *worse* (e.g. a future refactor accidentally removing the AI-document guard that currently prevents duplicates for that one path).
10. **XSS-safety of draft-rendered fields in Vue** — out of PHPUnit's reach entirely; no Dusk/JS test exists. The security audit found this correctly defended (Vue's default text interpolation), but that's a manual/architectural claim with no regression test of any kind backing it. Recommend a manual checklist item, not a blocking gap for this PR.
