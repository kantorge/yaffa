# Tag

## Feature Name

Tagging

## Feature Summary

A **Tag** is a lightweight, user-defined label that can be attached to transaction items to add extra meaning beyond the primary category. In YAFFA, tags help users group and revisit transactions across different contexts — for example, temporary projects, tax relevance, trips, reimbursements, or any other cross-cutting theme that does not fit neatly into the main category structure.

This makes tags a flexible overlay rather than a replacement for categories. They support YAFFA's philosophy of conscious financial tracking by helping users keep records rich enough for later review without forcing a rigid setup.

## Target User

- **Primary:**
  Intermediate to advanced personal finance users who review transactions regularly and want a second layer of organization beyond categories — especially for later filtering, reporting, and cleanup.

- **Secondary:**
  Daily-entry users who occasionally need quick flags or labels during transaction entry, without creating a new permanent category structure.

## User Problem

- A single category often cannot capture all the meaning a transaction item has.
- Users need a way to mark transactions across different categories with a shared theme.
- Some labels are situational or temporary and should not require a full category redesign.
- Finding all transactions related to one topic is difficult if the only structure is account, payee, and category.
- Users need a fast, low-friction way to add these labels during entry rather than only through later cleanup.

## User Value / Benefit

### Functional Benefits

- Lets users attach multiple labels to a transaction item for flexible filtering and review.
- Speeds up later analysis by making it easy to open all transactions associated with one tag.
- Supports quick creation and maintenance through a simple dedicated management screen.
- Allows older tags to be deactivated so selection lists stay clean without immediately losing history.
- Reduces the need to overload categories with secondary meanings that are better expressed as optional labels.

### Conceptual Benefits

- **Tags preserve nuance.** A category answers what a transaction was for; a tag can answer why it matters right now.
- **Tags support personal mental models.** Users can invent their own overlays without being forced into accounting-style structure.
- **Tags keep financial review intentional.** They make it easier to revisit spending by theme, project, or life event, which fits YAFFA's emphasis on active awareness rather than passive automation.

## Technical Description

A tag is a user-owned model with only a small set of properties: name, active state, and ownership. It is connected to transaction items through a many-to-many relationship, which means a single item can have multiple tags and the same tag can appear on many items.

Tags are managed through a standard CRUD web interface and a small API surface:

- a web list page with filtering and inline activation toggle
- create and edit forms
- an API endpoint for tag search/autocomplete
- an API endpoint for fetching a specific tag
- an API endpoint for toggling the active state

During transaction entry, the frontend uses a searchable multi-select field for tags. Existing tags are suggested via the API, and the UI also allows users to enter a new tag inline while saving the transaction.

## Inputs

- Tag name
- Active or inactive state
- Search text when looking up existing tags
- Optional request to include inactive tags in API results
- Selected tag values during transaction item entry
- New ad hoc tag text entered from the transaction form

## Outputs

- Created or updated tag records for the current user
- Tag-to-transaction-item associations in the pivot table
- Filterable tag lists in the management screen and API responses
- Transaction report links scoped to a selected tag
- Global search results including matching tags
- Transaction counts shown per tag in the list view

## Domain Concepts Used

- **Tag:** a lightweight secondary label applied to transaction items.
- **Transaction Item:** the specific line item inside a transaction that can be categorized and tagged.
- **Transaction:** the broader financial event; it can expose an aggregated view of tags from its items.
- **Active Tag:** a tag available for normal day-to-day selection.
- **Inactive Tag:** a retained but de-emphasized tag that is hidden from normal selection unless explicitly requested.

## Core Logic / Rules

- A tag belongs to exactly one user and is protected by ownership-based authorization.
- Authenticated and verified users can manage their own tags.
- Tag names are validated in the dedicated form flow and must be unique for that user.
- Tags have a boolean active flag and default to active in the create form.
- The normal API list returns only active tags unless the caller explicitly requests inactive ones too.
- Tags are attached to **transaction items**, not directly authored as standalone reporting objects.
- A transaction can still expose its tags by aggregating the tags from its items.
- Multiple tags can be attached to the same item.
- Tag management is intentionally lightweight: there is no hierarchy, merge flow, description field, or rich configuration.
- Unlike some core financial entities, a tag can be deleted even if it has already been used; removing it also removes its item associations.

## User Flow

### Creating and Maintaining Tags

1. The user opens the Tags page.
2. They create a new tag with a name and optional active state.
3. The list shows all their tags, whether they are active, and how many transactions use them.
4. The user can edit, deactivate, reactivate, or delete a tag later.

### Using Tags During Transaction Entry

1. The user creates or edits a transaction.
2. For each transaction item, they can choose one or more existing tags from a searchable selector.
3. If the needed tag does not exist yet, they can type a new one directly into the selector.
4. The saved transaction item keeps those tag associations for future review.

### Reviewing Tagged Activity

1. The user clicks a tag from the tag list or uses the transaction report filters.
2. YAFFA opens a filtered transaction view based on that tag.
3. The user reviews all related activity across accounts, payees, and categories.

## Edge Cases / Constraints

- Tags are optional; they enrich records but are not required for transaction entry.
- Because tags are secondary labels, they do not replace category-based meaning.
- Inactive tags are hidden from standard lookup results by default, which keeps selection cleaner but means older tags may be less visible unless explicitly included.
- Deleting a tag removes the labeling relationship from historical items, so tags are best treated as helpful overlays rather than permanent accounting anchors.
- The inline creation flow is deliberately lightweight, so tag governance is minimal by design.

## Dependencies

- **Models:** `Tag`, `User`, `TransactionItem`, `Transaction`
- **Web Controller:** `TagController`
- **API Controller:** `TagApiController`
- **Other Controllers:** `SearchController`, `TransactionApiController`
- **Request Validation:** `TagRequest`
- **Policy:** `TagPolicy`
- **Frontend:** tag index DataTable, transaction item tag selector, transaction report filters
- **External systems:** none

## Frontend Interaction

### Tag Management Screen

- Shows a table of tags with columns for name, active state, transaction count, and actions.
- Supports text filtering and active/inactive filtering.
- Allows one-click active-state toggling directly from the list.
- Provides quick actions to edit, delete, or open related transactions.

### Transaction Form

- Each transaction item includes a multi-select tag field.
- Existing tags are fetched asynchronously from the tag API.
- Users can choose multiple tags or type a new one inline.
- Existing saved tags are preloaded when editing a transaction.

### Reporting / Search

- Transaction reporting can be filtered by tag.
- Clicking a tag from the tag list opens a report scoped to that tag.
- Global search also returns matching tags by name.

## Domain Concepts

- **Primary categorization vs secondary labeling:** Categories define the main financial meaning of an item; tags add optional cross-cutting context.
- **Cross-cutting theme:** a theme that can span multiple categories or payees, such as a project, event, reimbursement process, or review marker.
- **Lightweight taxonomy:** a user-controlled set of labels with minimal rules and low maintenance overhead.

## Confidence Level

High

## Assumptions

- Example uses such as trips, taxes, or reimbursements are inferred from the role tags play in personal finance software; the code exposes flexible labeling but does not prescribe a fixed vocabulary.
- The product intent is aligned with the provided YAFFA context: tags are meant to assist understanding and review, not to automate away the user's engagement with their finances.
- No existing dedicated tag documentation was present in the AI docs folder, so this specification was derived directly from the code and nearby documentation patterns.
