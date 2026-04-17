# Payee

## Feature Name

Payee Management

## Feature Summary

A **Payee** is a named counterparty in a financial transaction — a merchant, employer, landlord, or anyone the user pays or receives money from. Naming payees gives transactions human meaning: instead of "€45 on Tuesday", the user sees "€45 at Supermarket" and can answer the question _where did my money go?_

Beyond naming, YAFFA allows users to associate category defaults and preferences with each payee, detect and suppress duplicates, merge mistakenly split payees, and rely on automatic payee recognition during bank import or AI document processing.

---

## Target User

- **Primary:**
  Any user recording standard income or expense transactions — from a complete beginner to an experienced tracker. Payees are a fundamental part of every withdrawal and deposit, so all users encounter them from day one. Even a first-time user naming "Supermarket" or "Employer" is already building a picture of their money flow. This is intentional: the dashboard onboarding checklist includes "Have some payees added" as one of the first setup steps, alongside currencies, account groups, and accounts — before the user records their first transaction.

- **Secondary:**
  Intermediate to advanced user who actively maintains the payee list over time — configuring category defaults and preferences, resolving duplicates, and using merge or import alias features. These users extract more value from payees through deliberate organization, not just through use.

---

## User Problem

- Without named counterparties, transactions are just amounts and dates — the user cannot answer "where did I spend?" or "what came in from where?", making meaningful financial review impossible
- Entering transaction details (especially categories) from scratch every time is slow and error-prone
- Over time, payees accumulate duplicates with slight name variations (especially from imports), making spending analysis unreliable
- Users need a way to signal which categories are more or less relevant for a specific payee, to guide future categorization
- Payees without a default category create a silent gap in financial organization that is easy to overlook

---

## User Value / Benefit

### Functional Benefits

- Reduces categorization effort: selecting a known payee pre-fills the default category, turning a multi-step task into one click
- Prevents duplicate payees by showing similar existing names while the user types, before the record is created
- Corrects existing duplicates in bulk via merge: all historical transactions are reassigned in a single operation — no manual transaction editing required
- Category suggestions remove the need to remember which payees still lack a default: the system proactively surfaces one payee at a time when patterns are clear enough
- Import alias ensures that payee names from bank exports map to the user's own named payees, preserving meaning built up over time

### Conceptual Benefits

- **Payees give transactions identity.** A transaction without a payee is an anonymous amount. A transaction linked to "Supermarket" or "Employer" is a meaningful financial event the user can recall, explain, and analyze.
- **Payees are the user's personal map of money flow.** The payee list represents every entity the user interacts with financially — a concrete, named picture of _where money comes from_ and _where it goes_.
- **Consistent payee naming makes spending analysis trustworthy.** Reports and summaries grouped by payee only work if the same merchant always appears under the same name. Payee management — including duplicate prevention and merge — directly enables this consistency.
- **Category preferences let users express financial intent.** Marking categories as preferred or excluded for a payee is a lightweight way to encode personal financial habits and expectations, without enforcing strict rules.

---

## Technical Description

A Payee is stored as two linked records: one holding the shared identity (name, active flag, alias, owner), and one holding payee-specific configuration (default category, suggestion-dismissed state). This structure mirrors how accounts are modeled, allowing both entity types to share common query and permission logic.

Each payee can have a list of preferred and excluded categories, stored as explicit user preferences. These are used to guide the categorization UI and to filter suggestions.

The category suggestion engine analyses transaction history per payee: when a single category dominates a payee's past transactions (above configurable thresholds), the system surfaces a suggestion in the payee list. The user can accept or dismiss it with one action.

---

## Inputs

- **User input:** payee name, active flag, import alias, default category, preferred categories, excluded categories
- **System input (suggestion engine):** historical transaction items linked to the payee (last 6 months by default)
- **System input (AI pipeline):** extracted payee name from a scanned/OCR'd document
- **System input (import):** payee name string from CSV/QIF import matched against the `alias` field

---

## Outputs

- Created or updated payee record (in `account_entities` + `payees` tables)
- Updated category preference pivot records
- Category suggestion surfaced in the payee list UI
- Default category set on payee config after suggestion acceptance
- `category_suggestion_dismissed` timestamp written on dismissal
- Merged payee: all `transaction_details_standard` records updated to point to target payee; source payee deleted or deactivated
- Payee API responses include: name, active state, alias, default category (with parent), preferred and excluded categories

---

## Domain Concepts Used

- **Payee:** A named entity representing where money goes (merchant, service provider) or comes from (employer, refund source) in a standard transaction.
- **Default Category:** The category a payee is most commonly associated with; pre-fills the category field when the payee is selected during transaction entry.
- **Category Preference:** The user's explicit signal about which categories are likely or unlikely for a payee. Guides the categorization UI without enforcing rules.
- **Category Suggestion:** A system-generated recommendation to assign a default category, based on observed dominance in past transactions.
- **Import Alias:** One or more alternate names a payee may appear under in bank exports. Used to map raw import data to the user's own named payees.
- **Transaction (standard):** A non-investment, non-transfer financial event where one side is the user's account and the other is a payee.

---

## Core Logic / Rules

### Payee Identity

- Name must be unique per user within the `payee` config type
- A payee belongs to exactly one user (tenant isolation)
- Payees can be active or inactive; inactive payees are excluded from selection by default in dropdowns and searches (unless `withInactive` flag is passed)

### Transaction Role

- In **withdrawals**: the payee is the recipient — where the money goes
- In **deposits**: the payee is the source — where the money comes from
- Transfers between the user's own accounts, and investment transactions, do not involve payees

### Category Association

- A payee has at most **one default category** (nullable)
- A payee can have many **preferred** and many **excluded** categories (via pivot, mutually exclusive)
- A category cannot appear in both preferred and excluded lists simultaneously
- An excluded category cannot be the same as the default category

### Category Suggestion Eligibility

- Payee must have **no default category**
- Suggestion must not have been previously dismissed (`category_suggestion_dismissed IS NULL`)
- Payee must be **active** (for the suggestion shown in the default suggestion endpoint)
- Minimum **5 transactions** involving the payee (in any combination of `from`/`to` roles) across all time
- At least **50% of those transactions** use the same category (dominance threshold)

### Duplicate Detection (creation form only)

- When typing a new payee name in the modal form, the UI queries `/api/v1/payees/similar` using PHP `similar_text()` similarity
- Top 5 matches are shown; user can select an existing payee instead of creating a new one
- Selecting an inactive payee via the "similar" list automatically reactivates it

### Merge

- Source and target must both belong to the authenticated user
- Source and target must be different payees
- All `account_from_id` and `account_to_id` references to the source are updated to the target in a database transaction
- After reassignment, source payee is either deleted (hard) or set to inactive, depending on user's choice
- This operation is explicitly irreversible

### Deletion

- A payee can only be deleted from the UI if it has **zero associated transactions**
- If transactions exist, deletion is blocked with an informational message

### Import Alias

- Free-text, multi-line field; each line can represent an alternate name
- Interpretation of the alias format is handled by the importer (CSV/QIF), not the payee model itself
- The field is stored as `alias` on the `account_entities` table

### Simplified Mode

- A reduced variant of the payee form exists for embedded contexts (e.g., creating a payee inline during transaction entry)
- In simplified mode, category preference controls are hidden — only name, active flag, and default category are editable
- Saving in simplified mode leaves any previously configured category preferences untouched

---

## User Flow (if applicable)

### Creating a Payee (full form, dedicated page)

1. User navigates to Payees list → clicks "New payee" (full-page form)
2. Fills in name, active flag, default category, import alias, preferred/excluded categories
3. Submits → redirected to payees list with success message

### Creating a Payee (modal, from payees list)

1. User clicks "New payee" button on the payees index page
2. A modal appears (Vue `PayeeForm` component, `action="new"`)
3. As user types a name, similar existing payees are shown; user can select one to avoid creating a duplicate
4. If creating new: form is submitted via API; list is updated in place without page reload

### Editing a Payee (inline modal, from list)

1. User right-clicks a row (or clicks the row icon) → context menu appears → "Edit"
2. Vue `PayeeForm` modal opens prefilled with payee data fetched from API
3. User edits and saves; row is updated in-place in the DataTable

### Accepting a Category Suggestion

1. Payee list column "Default category" shows a lightbulb icon (💡) and suggested category name if applicable
2. User clicks the checkmark button inline → AJAX call to `POST /api/v1/payees/{id}/category-suggestions/accept/{category}`
3. The payee row updates to show the newly accepted default category; suggestion indicator disappears

### Merging Payees

1. User navigates to Payees list → "Merge payees" button (full-page form) — or right-clicks a row → "Merge into another payee" (pre-fills the source payee)
2. User selects source (to be merged away) and target (to keep) payees via searchable dropdowns
3. Selects post-merge action: delete or deactivate source
4. Submits → all transactions reassigned; source handled; redirected to payees list

---

## Edge Cases / Constraints

- A payee with transactions **cannot be hard-deleted** from the UI (soft guard only; no database constraint enforces this)
- Category suggestion dismissal is per-payee, not per-suggestion; dismissing means no future suggestions appear for that payee until the flag is manually cleared (there is no UI to un-dismiss — assumption: this is an implicit edge case not fully handled)
- The `payeeSource` pre-fill on the merge form is optional; the page works both pre-filled and empty
- Inactive payees are still accessible in the merge form (withInactive flag is passed) to allow cleanup
- When merging, the category preferences of the source payee are not merged into the target — only transactions are reassigned

---

## Dependencies

- **Models:** `AccountEntity`, `Payee`, `Category`, `TransactionDetailStandard`, `Transaction`, `User`
- **Services:** `PayeeCategoryStatsService`, `PayeePersistenceService`, `AssetMatchingService` (AI pipeline), `ProcessDocumentService` (AI pipeline)
- **Form Requests:** `AccountEntityRequest`, `MergePayeesRequest`
- **API Controllers:** `PayeeApiController`, `PayeeStatsApiController`
- **Web Controller:** `AccountEntityController` (payee-related actions: index, create, store, edit, update, mergePayeesForm, mergePayees)
- **External systems:** AI provider (optional, for payee name matching during document processing)

---

## Frontend Interaction

### Payees Index Page

- Lists all payees with sidebar filters: Active / Has default category / Has category suggestion / Name search
- Columns: Name, Active, Default category (with suggestion indicator), transaction count (links to transaction report), first and last transaction dates, Import alias
- Context menu per row: Edit, Show transactions, Merge into another payee, Delete (only available if payee has never been used)
- Active flag can be toggled directly in the list without opening the edit form
- Two modal forms are embedded on the page: one for creating a new payee, one for editing an existing one

### Payee Form Modal

- Used for both creating and editing payees directly from the payees list (no page reload)
- Searchable dropdowns for default category, preferred categories, and excluded categories
- Preferred and excluded category lists are mutually exclusive
- While typing a name (creation only), similar existing payees are shown below the input to help the user avoid duplicates
- In simplified mode, category preferences are hidden; only name, active flag, and default category are shown

### Payee Form Page (full-page)

- Standalone create/edit page (accessible via direct URL routing)
- Same fields as the modal form, including category preferences; preferred and excluded lists are mutually exclusive

### Merge Form Page

- Two searchable dropdowns: "payee to be merged" (source) and "where to merge into" (target), each filtering out the other
- Radio button choice: delete the source after merging, or set it to inactive
- Irreversibility warning displayed inline before submission

---

## Domain Concepts

- **Payee:** A named counterparty in a standard transaction — a merchant, employer, utility provider, or any individual/organization the user transacts with. Not an account the user holds.
- **Default Category:** The single category pre-assigned to a payee; used as a shortcut when recording transactions involving that payee.
- **Category Preference:** A user-expressed signal that a category is particularly likely ("preferred") or not applicable ("excluded") for a given payee. Used to guide the categorization UI.
- **Category Suggestion:** A system-generated recommendation to assign a default category to a payee without one, based on statistical analysis of past transactions.
- **Import Alias:** An alternate payee name or set of names (one per line) used to map raw bank export data to a known payee record during CSV/QIF import.
- **Merge:** The operation of reassigning all transactions from one payee to another, then disposing of the original.

---

## Confidence Level

**High**

All core behaviors are directly verified in source code. The suggestion thresholds, merge logic, category preference constraints, and API contracts are all explicitly implemented and testable.

---

## Assumptions

- There is currently no dedicated "show" page for a payee — `AccountEntityController::show()` explicitly redirects back for payees. A payee's transactions are accessible via the reports page but not through a payee-specific detail view.
- The "un-dismiss suggestion" workflow has no UI implementation found in the codebase. Once a suggestion is dismissed, the only way to restore it would be through a direct database change.
- The `simplified` form mode appears to be used when `PayeeForm` is embedded in other contexts (e.g., inline creation during transaction entry), based on prop naming and behavior, though no specific host components were found in the reviewed files.
