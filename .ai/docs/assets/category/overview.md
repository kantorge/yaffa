# Category

## Feature Name

Category

## Feature Summary

Categories are the core way users give meaning to their financial activity in YAFFA. They let users label income and spending in a way that matches how they think about their life, so transactions become useful for review, reporting, budgeting, and long-term habit awareness rather than just being raw records.

In practice, categories help users answer questions like "What am I spending on?", "How has this changed over time?", and "Which areas of my finances need attention?" They also reduce repeated data entry by making category selection faster and more consistent.

## User Problem

- Raw transactions do not explain the purpose of spending or income.
- Repeated manual categorization becomes slow and frustrating over time.
- Users need a structure that supports both quick daily entry and later review.
- Spending analysis is hard if similar transactions are labeled inconsistently.
- Users sometimes refine their financial structure over time and need a safe way to reorganize it.

## Target User

- Primary: Intermediate personal finance users who record transactions regularly and want to understand patterns in spending and income over time.
- Primary: Detail-oriented users who want to shape their own financial structure rather than rely on a fixed categorization model.
- Secondary: Beginners who benefit from faster, guided categorization during transaction entry.

## User Value / Benefit

### Functional Benefits

- Makes transaction entry faster by reducing repeated category selection work.
- Makes reports meaningful by grouping transactions into understandable areas such as food, housing, salary, or transport.
- Helps maintain consistency, which improves trends, comparisons, and reviews.
- Supports reorganization over time without forcing users to lose historical data.
- Keeps historical transactions understandable even when a category is no longer actively used.

### Conceptual Benefits

- Turns financial records into a usable mental model of a user's life.
- Helps users see behavior, not just totals.
- Supports reflection: users can identify habits, recurring costs, and areas of change.
- Encourages intentional tracking, which matches YAFFA's goal of active financial awareness rather than passive automation.

## Concept Description

Category is the structure users apply to transaction items so their financial records reflect real-life meaning rather than just movement of money. A category can stand alone or sit under a broader parent category, which gives users flexibility without forcing a complex setup.

Across YAFFA, categories influence how money is entered, suggested, reviewed, compared, and interpreted over time.

## Inputs

- Category name
- Optional description (primarily used to guide AI during item categorization)
- Optional parent category (to set up a simple hierarchy)
- Active or inactive status
- User choice to merge one category into another when reorganizing
- Category selection during transaction entry

## Outputs

- Transactions become grouped into meaningful financial areas.
- Reports and summaries can show activity by category.
- Transaction entry surfaces relevant category suggestions.
- Users can browse their category list with usage context such as whether a category is still used.
- Historical records remain understandable even after cleanup or reorganization.

## Domain Concepts Used

- Category: a label that explains what a transaction item represents financially.
- Parent category: a broader grouping used to organize related categories.
- Active category: a category available for normal selection in day-to-day use.
- Inactive category: a category kept for historical meaning but not emphasized for future entry.
- Payee preference: a user-specific hint that some categories are more or less likely for a given payee.
- CategoryLearning: a separate derived concept used for AI-related suggestion behavior; documented separately.

## Core Logic / Rules

- A category belongs to one user and is part of that user's personal financial structure.
- Categories can be organized into a simple parent-child hierarchy.
- Categories can be renamed or reorganized as a user's understanding evolves.
- Categories can be made inactive instead of deleted, which preserves history while reducing clutter.
- A category cannot be deleted if doing so would break existing records or linked behavior.
- Categories can be merged so users can simplify or restructure their setup without manually editing old transactions one by one.
- Category suggestions are influenced by prior usage and by payee-related behavior.

## User Flow

### Creating and Maintaining Categories

1. The user creates categories that match how they think about their finances.
2. The user may optionally place a category under a broader parent to keep the structure organized.
3. Over time, the user can edit names, change structure, or mark older categories inactive.

### Using Categories During Transaction Entry

1. The user records a transaction.
2. The user selects a category for each transaction item.
3. The interface helps by surfacing relevant categories based on prior behavior.
4. The saved transaction becomes available for category-based review and analysis later.

### Reorganizing Categories

1. The user decides that two categories should be combined or that one should no longer be used.
2. The user merges the older category into the preferred one, or marks it inactive.
3. Existing history remains usable without requiring manual cleanup across past transactions.

## Edge Cases / Constraints

- Categories are not disposable labels once they are part of financial history; the system protects records that depend on them.
- Inactive categories remain relevant for past transactions even if they are no longer part of normal daily entry.
- Reorganizing categories is supported, but users must choose how to handle categories that are already in use.
- The category structure is intentionally modest rather than deeply hierarchical, which keeps it understandable for everyday personal finance use.
- CategoryLearning is related but separate; it should not be treated as part of the core Category concept itself.

## Related Product Behaviors

- Transaction entry uses categories to turn raw money movement into meaningful financial records.
- Reporting uses categories to summarize behavior over time.
- Payee-based suggestions use categories to reduce repetitive input.
- AI-assisted document processing can reference categories through the separate CategoryLearning concept.

## User Interaction

- Users manage categories through dedicated create, edit, list, and merge screens.
- During transaction entry, users pick categories from searchable suggestions.
- The category list gives users context about which categories are active, still used, or candidates for cleanup.
- Reports surface category-based views so users can review financial behavior over time.

## Confidence Level

High

## Assumptions

- The intended audience for categories is primarily end users managing personal finances, not administrators configuring system-wide taxonomy.
- Category hierarchy is meant to support clarity and lightweight structure, not deep accounting-style classification.
- CategoryLearning should remain documented separately even though users may experience it indirectly as smarter suggestions.

## Related Concepts

### CategoryLearning

CategoryLearning is a separate derived concept used for AI-assisted suggestion behavior around categorization. It builds on categories but is not part of the core definition of what a category is.

## Known Limitations / Open Questions

1. The structure appears intentionally shallow, which favors clarity but limits more complex multi-level taxonomy.
2. Category behavior is strong around classification and review, but category-specific budgeting rules are not clearly expressed as part of this core concept.
3. Some category-related intelligence is experienced indirectly through suggestions, which may blur the boundary between core category behavior and CategoryLearning in the product experience.

## Completeness Assessment

Complete

The Category concept is clearly established in the product and has a coherent user purpose: helping users classify transactions, preserve meaning over time, and analyze financial behavior in a way that supports YAFFA's emphasis on conscious financial tracking.
