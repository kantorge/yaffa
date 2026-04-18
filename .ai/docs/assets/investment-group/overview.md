# Investment Group

## Feature Name

Investment Group Organization

## Feature Summary

An Investment Group is a user-defined label for organizing holdings such as stocks, funds, ETFs, or other investments into meaningful buckets. It does not represent value on its own; instead, it gives structure to the user’s portfolio so investment tracking remains clear and manageable.

---

## Target User

- **Primary:**
  Intermediate and advanced users who track multiple holdings and want a clearer portfolio structure for review and long-term planning.

- **Secondary:**
  Beginner investors who only have a few assets but still want to separate them by purpose, account type, or strategy.

---

## User Problem

- A growing list of investments becomes difficult to understand without meaningful grouping.
- Users often think about holdings by strategy or purpose, such as retirement, dividend income, or speculative assets.
- Investment tracking is more useful when the portfolio has structure beyond individual symbols.

---

## User Value / Benefit

### Functional Benefits

- Lets users organize holdings into meaningful categories.
- Makes the investment list easier to review and maintain.
- Acts as a required prerequisite for creating investments, supporting a more consistent setup flow.
- Shows how many investments belong to each group in the management screen.

### Conceptual Benefits

- **Creates a portfolio-level mental model.** Users can understand not only what they own, but how those holdings are organized.
- **Supports long-term planning.** Grouping investments by purpose or strategy helps users review their broader allocation more intentionally.
- **Keeps manual investing records understandable.** This matches YAFFA’s philosophy of conscious, user-controlled tracking.

---

## Technical Description

An Investment Group is a user-owned model with a single editable attribute: name. It belongs to one user and has a one-to-many relationship with investments. The group is managed through standard list, create, and edit screens, while delete safety is handled by a dedicated service and API endpoint.

The list page loads the current user’s groups together with the count of related investments, and authorization prevents other users from accessing or modifying them.

---

## Inputs

### User-provided

- **Name** — required, unique per user, with minimum and maximum string length validation.

### System inputs

- The authenticated user context.
- Existing investments linked to the group.

---

## Outputs

- A saved investment group record tied to the current user.
- A management list showing the group name and number of linked investments.
- Validation or deletion error messages when rules are violated.

---

## Domain Concepts Used

- **Investment Group** — a logical bucket for organizing holdings.
- **Investment** — a tracked asset such as a stock, ETF, bond, or fund.
- **User ownership** — each group is private to the user who created it.
- **Portfolio structure** — the user’s chosen way of segmenting investments for clarity.

---

## Core Logic / Rules

- The group **belongs to exactly one user**.
- The **name is required** and must be **unique within that user’s investment groups**.
- Only the owner can list, create, edit, update, or delete the group.
- Guest users and unverified users cannot access the management flow.
- A group **cannot be deleted if it is already used by one or more investments**; YAFFA returns an error instead.
- The group itself has no pricing or performance behavior; it is strictly organizational.

---

## User Flow

1. User opens the Investment Groups section.
2. User adds a new group by entering a name.
3. The group becomes available in investment creation and editing forms.
4. User can rename the group later if their portfolio structure changes.
5. If no investments are attached anymore, the group can be deleted; otherwise YAFFA blocks the deletion.

---

## Edge Cases / Constraints

- Duplicate names for the same user are rejected.
- Empty names are rejected.
- Cross-user access is forbidden.
- Deletion fails when existing investments still reference the group.
- The group is not an investment itself and does not store quantity, price, or ROI.

---

## Dependencies

### Models:

- `InvestmentGroup`
- `Investment`
- `User`

### Services:

- `InvestmentGroupService`

### Controllers:

- `InvestmentGroupController`
- `API\\InvestmentGroupApiController`

### Policies:

- `InvestmentGroupPolicy`

### Frontend / Views:

- `investment-groups.index`
- `investment-groups.form`
- investment creation/editing forms that consume the available group list

---

## Frontend Interaction

- The user sees a dedicated Investment Groups list with:
  - a guided-tour card,
  - a “new investment group” action,
  - a shortcut to manage investments,
  - search/filter support,
  - a table of existing groups.
- Create and edit use the same simple one-field form.
- These groups appear as selectable options when managing investments.

---

## Domain Concepts

- **Retirement bucket:** an example grouping for long-term holdings.
- **Income bucket:** an example grouping for dividend-focused assets.
- **Portfolio organization:** the structure users apply to keep a diverse investment setup understandable.

---

## Confidence Level

**High**

---

## Assumptions (IMPORTANT)

- This specification used the product context in `.ai/docs/product-context.md` as a framing reference for user value. That context aligns with the code.
- It also referred to `.ai/docs/assets/investment/SPECIFICATION.md` for consistent terminology around the underlying investment concept.
- The concept appears **complete and stable**: it is intentionally lightweight and primarily supports organization, setup, and deletion safety.
