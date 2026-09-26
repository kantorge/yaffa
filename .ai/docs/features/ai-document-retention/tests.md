# AI Document Retention — Test Coverage Map

Stack: PHPUnit only (no Pest), Feature tests preferred, Dusk only for critical E2E (see `tests/CLAUDE.md`). Run: `vendor/bin/sail artisan test --compact <files>`.

**Verified today:** the five files below were run against this branch — 153 tests, 304 assertions, all passing. There is no JS test for the retention UI, and CI configuration was not inspected for this map (see "CI gate").

## Coverage table

| # | Use case | Rule (doc) | Expected behaviour (+ deny case) | Evidence | Type | Status |
|---|---|---|---|---|---|---|
| 1 | Purge finalized old doc | flows F4 | Row, files, received mail deleted | `CleanupOldAiDocumentFilesCommandTest::test_it_deletes_old_finalized_documents_with_their_files_and_received_mail` | integration | existing |
| 2 | Transaction survives | architecture "Data changes" | Transaction kept, `ai_document_id` null; also for manual delete | `…::test_it_keeps_the_transaction_of_a_deleted_document`, `AiDocumentStorageAndDeletionTest::test_deleting_a_document_keeps_its_transaction` | integration | existing |
| 3 | Directory cleanup limits | flows F4.3 | Emptied per-doc dir removed; dir with other content kept | `…::test_it_removes_the_emptied_document_directory_but_not_a_directory_with_other_content` | integration | existing |
| 4 | Path guard: other user's dir / traversal | permissions "Code-enforced" | File outside `ai_documents/{user}` untouched | `…::test_it_never_deletes_files_outside_the_users_document_directory` | integration | existing |
| 5 | Path guard: symlink | flows F4.1 | File reached through symlink untouched | `…::test_it_never_deletes_files_reached_through_a_symlink` | integration | existing |
| 6 | Disk failure rolls back | flows F4.2 | Document kept if files can't be deleted | `…::test_it_keeps_the_document_when_its_files_cannot_be_deleted` | integration | existing |
| 7 | Recent / recently-updated docs kept | architecture (`olderThan`) | Both timestamps must be older | `…::test_it_keeps_finalized_documents_that_are_recent_or_were_updated_recently` | integration | existing |
| 8 | Non-finalized never deleted; one reminder | flows F2.6/F5, emails | Kept; exactly one mail per user per run | `…::test_it_keeps_old_unprocessed_documents_and_sends_one_reminder_per_user` | integration | existing |
| 9 | Reminder link | emails | Link carries `status=unprocessed`, `date_to` | `…::test_reminder_links_to_the_unprocessed_filter_of_the_index_view` | integration | existing |
| 10 | No setting → no-op | flows F2.2/F2.4 | Nothing deleted, nothing dispatched | `…::test_it_does_nothing_for_users_without_a_retention_setting` | integration | existing |
| 11 | Per-user isolation | permissions "Where scope is derived" | Each user's own period; other users untouched | `…::test_each_user_gets_their_own_retention_period`, `…::test_it_can_scope_cleanup_to_a_specific_user` | integration | existing |
| 12 | Invalid `userId` | flows F2 | Command fails | `…::test_it_fails_when_user_id_is_invalid` | integration | existing |
| 13 | Setting validation | flows F1.2 | 1–3650 or null accepted; 0, negative, 3651, non-numeric → 422 | `AiUserSettingsApiV1Test::test_v1_update_rejects_invalid_document_retention_days` (+ set/clear test) | integration | existing |
| 14 | Setting resolved / exposed | architecture | `null` by default; value round-trips | `AiUserSettingsApiV1Test`, `AiUserSettingsResolverTest` | unit + integration | existing |
| 15 | Ability gate on settings + maintenance endpoints | permissions matrix | Token without `settings` → 403 | `ApiAbilityEnforcementTest` (rows `maintenance.cleanup-ai-document-old-files`, AI settings) | integration | existing |
| 16 | Unauthenticated cannot trigger cleanup; authenticated queues for own id | flows F3 | 401 / queued with caller id | `AiDocumentMaintenanceApiTest` (2 tests) | integration | existing |
| 17 | Text-input path fix | architecture "Data changes" | Stored under readable path; migration repairs `'1'` rows only | `AiDocumentStorageAndDeletionTest::test_text_input_is_stored_under_a_readable_file_path`, `::test_migration_repairs_text_input_file_paths_only` | integration | existing |
| 18 | Failed write leaves no document | flows (upload) | No orphan document; no job queued | `AiDocumentStorageAndDeletionTest::test_a_failed_file_write_leaves_no_document_behind_and_queues_nothing` | integration | existing |
| 19 | Maintenance page: Drive warning + disabled button | flows F3 | Warning only with enabled Drive config lacking post-import action; button disabled without period | `AiDocumentStorageAndDeletionTest::test_maintenance_page_*` (2 tests) | integration | existing |
| 20 | Job re-checks the setting at run time | flows F2.4 | Job dispatched, then setting cleared → no deletion (control: set again → deleted) | `CleanupOldAiDocumentFilesCommandTest::test_a_queued_job_does_nothing_if_the_setting_was_cleared_before_it_ran` | integration | existing |
| 21 | Schedule registered daily 03:30 | cron inventory | One event, `30 3 * * *`, only when `runs_scheduler` | `Console/AiDocumentCleanupScheduleTest` (2 tests) | integration | existing |
| 22 | Cross-user: doc whose `file_path` points into another user's directory (incl. `../` and `x/../../` forms) | permissions | Own doc deleted, other user's file intact | `…::test_it_never_deletes_files_outside_the_users_document_directory` (asserts `ai_documents/{other}/1/theirs.txt` still exists) | integration | existing |
| 23 | Legacy file names with `\` | architecture risk 4 | Row deleted, file skipped | `CleanupOldAiDocumentFilesCommandTest::test_a_file_with_a_backslash_in_its_name_is_left_alone_while_the_document_is_deleted` | integration | existing |
| 24 | Reminder repeats until handled | cron idempotency | Sent on every run; stops once finalized | `CleanupOldAiDocumentFilesCommandTest::test_the_reminder_is_sent_on_every_run_until_the_document_is_handled` | integration | existing |
| 25 | Drive filename cannot escape `ai_documents/{user}/{uuid}/` on import | architecture boundary (upstream) | `../`, `..\` and `/` in names are reduced to the last segment; nothing is written elsewhere | `ProcessGoogleDriveConfigJobTest::test_job_keeps_path_like_drive_file_names_inside_the_documents_own_directory` (fails against the unfixed job) | integration | existing |
| 26 | FK is `SET NULL` | architecture "Data changes" | Deleting a document keeps its transaction with `ai_document_id = null` | `AiDocumentStorageAndDeletionTest::test_deleting_a_document_keeps_its_transaction` (behavioural, uses the real FK). The migration's `down()` is not exercised: DDL implicitly commits inside the `RefreshDatabase` transaction | integration | existing (up only) |
| 27 | `unprocessed` pseudo-status filter in the table | flows F5 link target | Matches every non-finalized status | none (no JS test) | manual / JS unit | **none** |
| 28 | Settings-form Drive confirmation dialog | flows F1.5 | Shown only on change and when Drive keeps files | none | manual | **none** |
| 29 | Manual delete path guard | flows F6 | Not present in code — rule does not exist | n/a | — | gap (see below) |

## Proposed tests

None outstanding. Not proposed: a `down()`/`up()` round-trip of the FK migration (unsafe inside `RefreshDatabase` on MySQL, see row 26), and a test for empty nested directories left by legacy Drive names (accepted limitation, new imports cannot produce them).

## Recommended CI gate

Not inspected in detail; check `.github/workflows/` (if present) before applying. Suggestion (label: **for approval, not applied**): run `vendor/bin/sail`-equivalent `php artisan test --compact` (Feature + Unit, MySQL service) and `vendor/bin/phpstan analyse` + `vendor/bin/pint --test` on every PR; keep `tests/Browser` (Dusk) out of the required set; require the check on `develop` and `release/*` via branch protection. Everything above in the "existing" and "proposed" rows is deterministic and belongs in the required set. No row here needs a live service.

## Gaps — documented but unverified (ranked by exposure)

1. **Manual delete vs. purge parity (F6)** — manual delete removes files without a path guard; not documented as a rule, not tested.
2. **Frontend filter and confirm dialog (#27, #28)** — only reviewable manually; the confirm is the sole UI protection for Drive re-import.
