# Payee Category Recommendation Widget

## Feature Name

Payee Default Category Suggestion

## Feature Summary

This widget suggests a likely default category for a payee when the user's historical behavior strongly points to one dominant category. It helps reduce future categorization effort while preserving the user's control over whether the suggestion is accepted.

## Target User

- Primary:
  Users with repeated transactions for the same payees who want faster and more consistent categorization.

- Secondary:
  Users gradually cleaning up their finance data quality over time.

## User Problem

- Repeated payees often use the same category, but users may not have set that default explicitly.
- Inconsistent payee defaults increase manual effort and reduce reporting quality over time.

## User Value / Benefit

### Functional Benefits

- Detects a dominant category pattern for a payee.
- Lets the user accept the suggestion, ignore it for now, or dismiss it permanently for that payee.
- Improves future default categorization when accepted.

### Conceptual Benefits

- Rewards careful historical recording by learning from repeated patterns.
- Improves future reporting quality because cleaner categorization produces more trustworthy analysis.

## When to Use This Widget

Use this widget when the user wants to improve data quality and reduce repeated manual categorization work.

## Business Questions Answered

- Is there a payee I almost always categorize the same way?
- Can I simplify future transaction entry without losing control?
- Is the system learning useful habits from my recorded history?

## Technical Description

- The widget requests one eligible payee suggestion from the payee suggestion API.
- Suggestions are based on aggregated historical category usage by payee.
- A suggestion only appears when there is enough transaction history and one category clearly dominates.
- Accepted suggestions update the payee's default category, while dismissed suggestions are suppressed for that payee.

## Inputs

- Historical transactions by payee
- Category usage counts for the payee
- Payee state, including whether suggestions were previously dismissed

## Outputs

- Suggestion card with payee and proposed category
- Accept, maybe later, or dismiss actions
- Success or error feedback

## Core Logic / Rules

- Suggestions are only shown for eligible payees that do not already have a default category.
- The feature depends on a meaningful level of transaction history and a strong enough dominant-category pattern.
- The user remains in control; suggestions are optional, not automatically applied.

## Confidence Level

High

## Assumptions

- The widget is intended as a lightweight learning aid rather than a full recommendation engine.
