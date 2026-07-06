# Security Audit: whole repository (feat/qif-csv-import)

Date: 2026-07-02
Method: 6 parallel cluster audits (import, AI documents, investment providers/Google Drive,
core financial entities, auth/session/config, frontend Vue/JS) + manual merge and self-refute pass.

---

## app/Services/InvestmentPriceProviders/WebScrapingProvider.php

1. [CRITICAL] SSRF + internal-response exfiltration — `fetchPrices()` lines 28-59, `parsePriceValue()` lines 201-224
   Risk Level: Critical
   Attack Scenario: Any authenticated + email-verified user configures an investment's price provider as `web_scraping` (or calls `POST /api/v1/investment-price-providers/web_scraping/test-fetch`, gated only by `auth:sanctum`+`verified`) with `provider_settings.url = http://169.254.169.254/latest/meta-data/iam/security-credentials/...` (or any RFC1918/loopback address, an internal Redis/DB admin panel, etc.) and a broad CSS selector (e.g. `body`). `ScraperService::scrape()` (`app/Services/ScraperService.php:15-26`) passes the URL straight into `Roach::collectSpider(..., new Overrides(startUrls: [$url]))` with **no scheme/host/IP validation anywhere in this file or `InvestmentPriceProviderContextResolver::resolveAndValidateInvestmentSettings()`** (which only runs `FILTER_VALIDATE_URL`, accepting any host including private/link-local ranges). When the scraped content isn't a valid numeric price, `parsePriceValue()` throws `InvalidPriceDataException` embedding the **raw scraped page text**, which `InvestmentPriceProviderApiController::testFetch()` (lines 96-103) catches and returns verbatim in the JSON `error.message` to the caller.
   Impact: Full SSRF with response-content exfiltration — any low-privilege verified user can read cloud metadata credentials, internal admin interfaces, or other services reachable from the app server, and see the response body directly in the API response. Contrast with `GenericApiProvider` in the same directory, which has a (partial) private-IP guard — this provider has none at all.
   Solution: Add the same `assertPublicEndpointUrl()`-style host/IP allowlist check (block loopback, private, link-local, and reserved ranges; restrict scheme to `http`/`https`) to `ScraperService::scrape()` before dispatching the spider, and stop returning raw exception messages / scraped content in `testFetch()`'s error response — log server-side, return a generic message to the client.

## app/Services/InvestmentPriceProviders/GenericApiProvider.php

2. [HIGH] SSRF via unvalidated HTTP redirect (check-then-fetch bypass) — `fetchPrices()` lines 54-65; Guzzle client in `app/Providers/InvestmentPriceProviderServiceProvider.php:40-46`
   Risk Level: High
   Attack Scenario: `assertPublicEndpointUrl()` (lines 478-515) validates only the initial resolved host/IP of `endpoint_url`. The Guzzle client used for the actual request has no `allow_redirects => false`, so Guzzle follows up to 5 redirects by default. An attacker configures `endpoint_url` to a public host they control that passes the pre-check, then responds `302 Location: http://169.254.169.254/...`; Guzzle follows it without re-validating the new host.
   Impact: Bypasses the existing SSRF guard with a trivial, well-known technique — no DNS trickery required.
   Solution: Set `'allow_redirects' => false` on the Guzzle client (or `['max' => 0]`), or re-validate every redirect target through `assertPublicEndpointUrl()` via a Guzzle `on_headers`/redirect middleware.

3. [MEDIUM] SSRF via DNS-rebinding TOCTOU — `assertPublicEndpointUrl()`/`resolveEndpointIps()` lines 478-552 vs. the request at line 65
   Risk Level: Medium
   Attack Scenario: The private-IP guard resolves DNS itself at validation time, then hands the URL string (not a pinned IP) to Guzzle/cURL, which re-resolves DNS at connect time. A domain with a very short TTL can return a public IP for the validation lookup and a private IP moments later for the actual connection.
   Impact: Same as #2 but requires attacker-controlled DNS infrastructure — real, but higher effort.
   Solution: Resolve the hostname once, validate the IP, then connect directly to that pinned IP (e.g. via a Guzzle `curl` option or a custom DNS resolver) rather than letting the HTTP client re-resolve.

## app/Services/GoogleDriveService.php

4. [HIGH] SSRF via attacker-controlled `token_uri`/`auth_uri` in service-account JSON — `createClient()` lines 891-898; validation in `GoogleDriveConfigRequest::validateServiceAccountJson()` lines 137-162 and `GoogleDriveConfigApiController::validateServiceAccountPayload()` lines 514-542
   Risk Level: High
   Attack Scenario: Any verified user submits a "service account JSON" via `POST /api/v1/google-drive/config/folder-name`, `/config/folders`, `/test`, or `store`/`update`. Validation only checks that required keys are present and non-empty and `type === 'service_account'` — it never validates that `token_uri`/`auth_uri` point at a Google host. `Google\Client::setAuthConfig()` uses `token_uri` to POST a signed JWT bearer assertion whenever the client authenticates (triggered immediately by `getFolderName()`/`listFolders()`/`testConnection()`), so a crafted `token_uri` makes the server issue an authenticated POST to arbitrary internal infrastructure. Error responses (`test()`, folder lookups) echo `$e->getMessage()` back to the user.
   Impact: Same class of impact as findings #1-3 (internal network access from the server), reached through a field most reviewers wouldn't think to check as a "URL" input.
   Solution: After parsing the submitted JSON, validate `token_uri`/`auth_uri` against an allowlist of known Google OAuth endpoints (`https://oauth2.googleapis.com/token`, `https://accounts.google.com/o/oauth2/auth`) before calling `setAuthConfig()`.

5. [LOW] Drive API query-string injection via `folder_id`/`parent_id` — `listNewFiles()` line 46, `listFoldersByCredentials()` lines 373-381, `testConnection()` line 453
   Risk Level: Low
   Attack Scenario: `folder_id`/`parent_id` (validated only as `string, max:255`) is interpolated unescaped into Drive's `q` search syntax. A value containing a `'` can alter the query.
   Impact: Confined to the submitting user's own service-account-scoped Drive search — no cross-user or cross-tenant impact, so this does not meet the bar for a standalone finding, but worth a quick fix (escape single quotes) for correctness.

## app/Http/Controllers/TransactionController.php

6. [HIGH] IDOR — unscoped `AccountEntity::find()` leaks another user's account/payee data — `createFromDraft()` lines 201-206
   Risk Level: High
   Attack Scenario: `POST /transactions/create-from-draft` requires only `auth`+`verified` (no policy/ownership check on this action, unlike every other transaction-mutating action in the same controller). The controller accepts an arbitrary JSON blob in the `transaction` field and, for a standard-type draft, calls `AccountEntity::find($transactionData['config']['account_from_id'])` / `...account_to_id` with **no `where('user_id', ...)` scoping and no `Gate::authorize`**. The resolved model is attached to the in-memory `$transaction` and rendered directly into the page: `:transaction = "{{ $transaction }}"` in `resources/views/transactions/form.blade.php:42`. `AccountEntity::$hidden` only hides `config_id` (`app/Models/AccountEntity.php:92`), so `name`, `active`, `user_id`, and `config_type` for a victim's account/payee are embedded (HTML-escaped, but readable via view-source/devtools) in the attacker's own page.
   Impact: Any authenticated+verified user can enumerate sequential/guessable account/payee IDs and learn another user's account and payee names, active status, and internal user_id — cross-user data disclosure. `buildDraftTransactionItems` in the same controller correctly scopes an equivalent `Category` lookup by `user_id`, showing the guard was known but not applied consistently here.
   Solution: Scope both lookups: `AccountEntity::where('user_id', $request->user()->id)->find($transactionData['config']['account_from_id'])`, matching the pattern already used for categories a few lines above.

## app/Components/MailHandler.php + resources/js/ai-documents/components/AiDocumentEmailViewer.vue

7. [CRITICAL] Stored XSS via unsanitized inbound email HTML — write path `app/Components/MailHandler.php:21-27`, read path `AiDocumentEmailViewer.vue:57`
   Risk Level: Critical
   Attack Scenario: `MailHandler::__invoke` stores the raw MIME HTML body of any inbound email (`$email->html()`) directly into `ReceivedMail.html` with zero sanitization. The target user is resolved purely by `User::where('email', $email->from())->first()` — no DKIM/SPF/sender verification in this class. The only HTML-cleaning routine in the codebase, `CreateAiDocumentFromSource::cleanHtmlContent()` (`app/Listeners/CreateAiDocumentFromSource.php:114-142`), is applied only to a **separate copy** used for the LLM prompt text file — it never touches the `ReceivedMail.html` column. `AiDocumentApiController::show()` / `AiDocumentController::show()` return the `ReceivedMail` model with no filtering on `html` (`app/Models/ReceivedMail.php` has no `$hidden`, no sanitization). `AiDocumentEmailViewer.vue:57` renders it with `v-html="receivedMail.html"`. No HTML-sanitization library (DOMPurify or equivalent) exists anywhere in `resources/js` or `package.json` (verified by grep — zero hits) — there is no defense-in-depth on the frontend either.
   Impact: An attacker who can send email to the app's configured inbound address (with a `From:` matching or spoofing a victim user's email, if the mail provider doesn't enforce SPF/DKIM upstream of the webhook) can plant `<script>`/`<img onerror>` payloads that execute in the victim's browser, same-origin, when they open the AI document's Email tab — full session compromise (CSRF token theft, arbitrary same-origin actions).
   Also an **intended-vs-implemented gap**: `TECHNICAL.md:527` states HTML cleanup is "performed... before storing email content" — true for the LLM-facing text file, false for the `ReceivedMail.html` column actually rendered to the user.
   Solution: Run `ReceivedMail.html` through an HTML sanitizer (e.g. `ezyang/htmlpurifier` server-side, allowlisting only safe tags/attributes and stripping `<script>`, event handlers, and `javascript:`/`data:` URIs) before storage, and additionally sanitize client-side (e.g. DOMPurify) before the `v-html` render as defense in depth.

---

## Not standalone findings (checked, refuted, or config-dependent — see notes)

- **Password reset / registration: no rate limiting** (`ForgotPasswordController`, `RegisterController`) — real gap (no throttle middleware on `POST /password/email` or registration; reCAPTCHA is opt-in and blank by default), enabling mail-bombing of a victim's inbox and account enumeration via differing responses. Kept as a **Medium** finding: add `throttle:6,1`-style middleware to both routes.
- **AI prompt injection** (`AiPromptBuilder`, `AiImportProfileSuggestionService::buildPrompt`) — file/OCR/email content is attacker-influenceable and concatenated into prompts with only light delimiting. Refuted as a standalone Critical/High finding: outputs are constrained by Prism structured-output schemas (enums, typed fields), `AiExtractionSchemaValidator` and `sanitizeResponse()` strip out-of-schema values, category/account IDs the AI returns are re-validated against the user's own scoped records before use (`ProcessDocumentService.php:840-853`), and nothing downstream `eval`s or shell-execs AI output. Worth hardening (explicit "untrusted data" delimiters) but no concrete exploit path found today.
- **`ProcessDocumentService::matchAccount/matchPayee/matchInvestment`** accept the AI's raw numeric response as an ID without checking it's one of the offered candidates. Refuted as standalone: `TransactionRequest` re-validates every FK with `Rule::exists(...)->where('user_id', ...)` before a transaction is actually created, so a hallucinated/foreign ID is only ever pre-filled into the same `create-from-draft` form covered by finding #6 above, not a separate hole.
- **Toast pipeline (`bootstrap.js:35`, `showToast`) renders `innerHTML` with zero output encoding**, used by ~60 call sites app-wide. Traced the highest-risk call sites (AI document reprocess success/error, file-import-profile save/delete/suggest errors) back to their backend sources: all return static, translated (`__(...)`) strings or generic hardcoded text — no attacker-controlled or cross-user data currently reaches this sink. Refuted as a standalone finding for lack of a live exploit path, but flagged in the root-cause theme below — the sink itself has no escaping, so it is one careless future change away from XSS.
- **`AiDocumentFileViewer.vue` iframe has no `sandbox` attribute**, and `AiDocumentController::file()` serves uploaded files `inline` via `response()->file()` (Content-Type auto-detected from file content). Refuted: default `AI_DOCUMENT_ALLOWED_TYPES=pdf,jpg,jpeg,png,txt` (`.env.example:109`) combined with Laravel's content-based `mimes:` validation blocks SVG/HTML uploads under default configuration. Flagged only as a hardening note for self-hosted instances that widen the allowlist — add `sandbox="allow-same-origin"`-free iframe sandboxing and consider forcing `Content-Disposition: attachment` for non-image/PDF types.
- **`config_type` on `AccountEntity` is mass-assignable and can be flipped independently of `config_id`** (`AccountEntityController::update()`), causing polymorphic type confusion. Refuted: same-user only, no cross-tenant or shared-system impact — a data-integrity bug, not a security finding under this audit's criteria.
- **CSRF exempted for `/telescope/*`** (`bootstrap/app.php:37-39`). Refuted: Telescope is disabled by default (`TELESCOPE_ENABLED=FALSE`) and additionally gated behind an admin-email `Gate::define('viewTelescope', ...)`. Real guards exist; noted only as a latent risk if Telescope is ever enabled without re-adding CSRF protection.
- **`routes/api.php` `v1` group has no group-level `auth:sanctum` fallback**, relying on every controller declaring it individually. Refuted per audit guidance against generic hardening with no concrete impact: verified all ~24 API controllers currently declare it consistently. Noted as fragile-but-not-broken.
- **Session cookie `secure` flag / trusted-proxy config** — not scored: depends on the deployed `.env`, which was correctly out of scope for a static repo review (only `.env.example` was checked, per the audit's own rules).

---

## Root-cause theme

The two Critical findings share a pattern: **user-influenceable destinations/content reach a powerful sink with no boundary check at all**, in modules that were added incrementally alongside better-guarded siblings — `WebScrapingProvider` has no SSRF guard while `GenericApiProvider` right next to it does; `ReceivedMail.html` is stored raw while the same email's text is sanitized for the LLM prompt a few lines away in the same listener. When you build the second/third integration in a family (price providers, AI-ingestion sources), the validation from the first one doesn't automatically propagate — worth a quick pass to make sure every "new fetch source" or "new content-storage path" added to these families gets the same guard as its siblings before merge, rather than auditing after the fact.

The IDOR in `createFromDraft` fits the same shape: the sibling method three lines above it (`buildDraftTransactionItems`) does the correct `where('user_id', ...)` scoping, but the guard wasn't copied to the two `AccountEntity::find()` calls right after it.

## What's well-built

- **Authorization/IDOR discipline is otherwise strong and consistent.** Every policy checked (`AccountEntityPolicy`, `TransactionPolicy`, `CategoryPolicy`, `TagPolicy`, `InvestmentPolicy`, `InvestmentGroupPolicy`, `CurrencyPolicy`, `AiDocumentPolicy`, `AiProviderConfigPolicy`, `AiUserSettingsPolicy`, `ReceivedMailPolicy`, `FileImportProfilePolicy`, `ImportPolicy`, `InvestmentProviderConfigPolicy`, `GoogleDriveConfigPolicy`) implements real `user_id` ownership checks, not just existence checks, and is applied consistently across ~40 controllers. Merge/bulk-mutation endpoints (payee merge, category merge, category-learning merge) validate ownership of both source and target on every affected row via `Rule::exists(...)->where('user_id', ...)`, not just the primary record.
- **Secrets handling is correct.** `AiProviderConfig.api_key`, `InvestmentProviderConfig.credentials`, and `GoogleDriveConfig.service_account_json` are all `encrypted` casts, all `$hidden`, and all confirmed absent from every API Resource — no plaintext key ever observed in a response or log statement across three independent cluster reviews.
- **Mass-assignment defenses are layered.** `user_id`, `type`, and other server-derived fields are consistently `prohibited` in Form Requests *and* absent from model `$fillable` *and* set explicitly server-side — the "belt and suspenders" pattern shows up in the import-profile, transaction, and account-entity code paths alike.
- **The import feature (the branch's main new surface) is clean.** No eval/dynamic-include/unsafe-deserialization in the CSV/QIF parsers, file-size/row caps enforced mid-stream (not just upfront), system-only transform/rule execution genuinely unreachable by user-submitted profiles, and no `v-html`/`innerHTML` anywhere in `resources/js/import/`.
- **SQL injection surface is essentially nil** — every raw/`whereRaw`/`orderByRaw` usage found uses parameter bindings or an explicit column whitelist.

## What to double-check yourselves

- **Whether the configured mail provider (mailgun/sendgrid/postmark) enforces SPF/DKIM upstream of the `MailHandler` webhook** — this determines whether finding #7 requires a spoofed sender or a genuinely compromised mailbox. Not visible from this repo.
- **Whether this is genuinely single-tenant-per-instance in your deployments, or whether multiple unrelated users register on the same instance** — several findings above (SSRF, IDOR, email XSS) assume a real "victim" is a different registered user; if every instance only ever has one real user, the cross-tenant severity of some findings drops (SSRF into shared infrastructure still stands regardless).
- **RoachPHP's underlying downloader** (vendor code, not read in full) — if it has its own redirect/SSRF protections independent of `WebScrapingProvider`, finding #1 may be partially mitigated; worth a 10-minute check of `vendor/roach-php/core/src/Downloader` before treating it as fully open.
