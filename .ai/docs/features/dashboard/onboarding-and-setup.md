# Onboarding and Setup Guidance

## Feature Name

Dashboard Welcome and Readiness Checklist

## Feature Summary

This dashboard content helps new users understand what is still missing before YAFFA can provide useful insight. It turns initial setup into a visible checklist so the application feels guided instead of empty.

## Target User

- Primary:
  New users during first-time setup.

- Secondary:
  Returning users who want to confirm whether the essential finance structure is complete.

## User Problem

- A personal finance system only becomes meaningful after a few key foundations are set up.
- New users may not know what to do first or why the dashboard feels incomplete.

## User Value / Benefit

### Functional Benefits

- Lists the core setup tasks needed for meaningful use.
- Links directly to the relevant create or review pages.
- Supports a dismissible guided tour for dashboard orientation.

### Conceptual Benefits

- Reduces first-use confusion.
- Helps users understand that good reporting depends on a few essential setup steps being completed.

## Core Checklist Areas

- add at least one currency,
- set a base currency,
- create an account group,
- create at least one account,
- add payees,
- add categories,
- record the first transaction.

## Technical Description

- The widget loads onboarding state from the onboarding API using the dashboard topic.
- Steps are marked complete based on actual user data rather than static assumptions.
- A guided tour can be launched when the relevant onboarding step supports it.
- Users can temporarily hide or permanently dismiss the widget.

## Confidence Level

High

## Assumptions

- The onboarding card is intended to remain lightweight and reusable across several feature areas, with the dashboard version focused on first-use readiness.
