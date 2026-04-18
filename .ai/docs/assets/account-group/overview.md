# Account Group

## Feature Name

Account Group Organization

## Feature Summary

An Account Group is a simple user-defined label used to organize financial accounts into meaningful buckets such as daily banking, savings, or investments. It does not hold money itself; instead, it helps the user structure their account list so YAFFA stays understandable as their setup grows.

---

## Target User

- **Primary:**
  Beginner YAFFA users during setup or cleanup of their financial structure, especially people tracking multiple bank accounts, wallets, or savings containers.

- **Secondary:**
  Advanced, detail-oriented users who want a clearer hierarchy for periodic review and reporting.

---

## User Problem

- A flat list of accounts becomes hard to scan once the user tracks several financial containers.
- Users need a way to reflect how they mentally separate money: everyday spending, savings, emergency funds, brokerage cash, and similar categories.
- Account setup should remain structured without forcing a complex accounting model.

---

## User Value / Benefit

### Functional Benefits

- Lets the user classify accounts into logical buckets.
- Makes the account list easier to navigate and review.
- Provides a required structural step before creating accounts, ensuring consistent setup.
- Shows how many account entries belong to each group in the management screen.

### Conceptual Benefits

- **Turns a flat account list into a mental map.** Users can recognize where their money sits by purpose, not only by name.
- **Supports intentional organization.** This fits YAFFA’s philosophy of conscious, manual financial tracking rather than opaque automation.
- **Improves long-term clarity.** Grouping accounts helps users keep a stable structure as their finances evolve.

---

## Technical Description

An Account Group is a user-owned model with a single editable field: its name. It belongs to one user and is referenced by account configuration records. In practice, the web UI provides standard create, edit, and list screens, while deletion safety is enforced by service logic and API endpoints.

The list page loads the user’s groups together with a count of related account entities, and ownership is enforced through policies and user scoping.

---

## Inputs

### User-provided

- **Name** — required, unique per user, with minimum and maximum string length validation.

### System inputs

- The authenticated user context.
- Existing accounts linked to the group.

---

## Outputs

- A saved account group record tied to the current user.
- A management list showing the group name and usage count.
- Validation or deletion error messages when the action is not allowed.

---

## Domain Concepts Used

- **Account Group** — a logical bucket for organizing accounts.
- **Account** — a financial container such as a bank account, wallet, or savings account.
- **Account Entity** — the shared identity layer through which account usage is counted.
- **User ownership** — each group is private to the user who created it.

---

## Core Logic / Rules

- The group **belongs to exactly one user**.
- The **name is required** and must be **unique within that user’s account groups**.
- Only the owner can view, edit, update, or delete the group.
- Guest users and unverified users cannot access management flows.
- A group **cannot be deleted if it is already used by an attached account**; YAFFA returns an error instead of removing it.
- The web flow supports standard CRUD-style management, with additional delete protection exposed through the API/service layer.

---

## User Flow

1. User opens the Account Groups section.
2. User creates a new group by entering a name.
3. The group becomes available in account creation and editing forms.
4. User can later rename the group if their structure changes.
5. If the group is no longer used, it can be deleted; if still attached to accounts, YAFFA blocks the deletion.

---

## Edge Cases / Constraints

- Duplicate names for the same user are rejected.
- Empty names are rejected.
- Cross-user access is forbidden.
- Deletion fails when related accounts still depend on the group.
- The group is organizational only; it does not store balances, transactions, or financial calculations.

---

## Dependencies

### Models:

- `AccountGroup`
- `User`
- `Account`
- `AccountEntity`

### Services:

- `AccountGroupService`

### Controllers:

- `AccountGroupController`
- `API\\AccountGroupApiController`

### Policies:

- `AccountGroupPolicy`

### Frontend / Views:

- `account-groups.index`
- `account-groups.form`
- account creation/editing forms that consume the available group list

---

## Frontend Interaction

- The user sees a dedicated Account Groups list with:
  - a guided-tour card,
  - a “new account group” action,
  - search/filter support,
  - a table of existing groups.
- Create and edit use the same simple form with one field: name.
- These groups appear as selectable options when managing accounts.

---

## Domain Concepts

- **Daily banking group:** an example mental bucket for frequently used spending accounts.
- **Savings group:** an example bucket for money held for future goals or reserves.
- **Organizational structure:** the user-defined hierarchy that keeps accounts understandable without changing the underlying financial data.

---

## Confidence Level

**High**

---

## Assumptions (IMPORTANT)

- This specification used the product context in `.ai/docs/product-context.md` as a framing reference for user value. That context aligns with the code.
- It also referred to `.ai/docs/assets/account/SPECIFICATION.md` for consistent terminology around what an account represents in YAFFA.
- The concept appears **complete and stable**: it is intentionally simple, and most of its value comes from organization, ownership, and safe deletion behavior rather than complex logic.
