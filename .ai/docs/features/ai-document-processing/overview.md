# AI Document Processing (MVP)

## Feature Summary

AI Document Processing helps users turn receipts, invoices, email confirmations, and imported files into draft transactions with much less manual typing. It supports YAFFA’s product philosophy by assisting with entry and categorization while keeping the user in control of final approval.

**Current Implementation Status:** This feature is **MVP complete**. Core document intake, draft creation, review, finalization, learning, and notification flows are available.

## Target User

- Primary: Intermediate or advanced YAFFA users who regularly record expenses and want to reduce repetitive receipt entry without giving up review control.
- Secondary: Self-hosting and privacy-conscious users who want optional AI assistance for document-based transaction capture.

## User Problem

- Manually typing transactions from receipts, invoices, and email confirmations takes time.
- Multi-item purchases are tedious to split and categorize consistently.
- Important proof documents can be lost after the transaction is entered.
- Users want automation support without turning budgeting into a “black box”.

## User Value / Benefit

- Reduces manual transaction entry from a full copy-and-type task to a review-and-confirm step.
- Keeps the human decision in the loop so users still understand what enters their financial history.
- Makes categorization more consistent over time by reusing prior decisions.
- Connects the final transaction back to its source document for later review and trust.

#### Functional Benefits

- Upload documents from multiple sources and receive a prepared draft transaction.
- Review extracted payee, date, amount, and item-level suggestions before saving.
- Receive warnings about likely duplicate transactions before finalizing.
- Reprocess failed documents instead of starting over.

#### Conceptual Benefits

- Helps users maintain financial awareness instead of fully delegating transaction entry.
- Preserves an audit trail between real-world evidence and recorded financial activity.
- Reinforces consistent categorization, which supports reporting, budgeting, and long-term planning.

## Goals / Non-Goals

### Goals

- Help users turn everyday financial documents into reviewable transaction drafts.
- Support receipt item breakdown when it adds budgeting value.
- Learn from accepted categorizations to reduce repeat work.
- Support optional background importing from email and Google Drive.
- Keep the workflow user-controlled, review-first, and suitable for self-hosted finance tracking.

### Non-Goals

- Full bank-statement or multi-transaction import from a single document.
- Fully automatic posting without user review.
- Replacing the existing transaction editing experience with a new standalone finance workflow.
- Acting as a generic file-storage or notification platform.

## Inputs

- Manually uploaded receipts, invoices, screenshots, PDFs, or text files
- Email-based purchase confirmations and receipts
- Files imported from a user-configured Google Drive folder
- Optional user guidance through a custom prompt

## Outputs

- A draft transaction prepared for user review
- Suggested payee, amounts, date, accounts, and categories
- Duplicate warnings before save
- A finalized transaction linked back to the original document
- Email feedback when processing succeeds or fails

## Domain Concepts Used

- AI document: a submitted source document waiting for review or already finalized
- Draft transaction: a prefilled transaction that still requires user confirmation
- Category learning: reuse of previously accepted category choices for similar items
- Source link: the connection between the stored document and the saved transaction

## Core Logic / Rules

- One submission produces one reviewable draft transaction in the MVP.
- Users do not directly switch document status; statuses change through processing, review, reprocessing, and finalization.
- A failed document cannot be finalized as-is; it must be reprocessed or handled separately.
- AI suggestions assist the user, but the final transaction is only created after human review and save.
- Learning improves from accepted outcomes, not from unreviewed guesses.

## User Flow

1. The user uploads a document, forwards an email, or enables Google Drive import.
2. YAFFA processes the document in the background and prepares a draft.
3. The user opens the review screen and checks the extracted details.
4. The user edits any fields, confirms or rejects suggestions, and reviews duplicate warnings.
5. The user saves the transaction, and the document is marked as finalized.

## Frontend Interaction

- A document list shows submitted and processed items.
- A review view lets the user inspect extracted details and launch the existing transaction form.
- AI-related settings are managed from a dedicated settings area.
- The final save experience stays aligned with YAFFA’s existing transaction entry flow.

## Edge Cases / Constraints

- The MVP focuses on one transaction per submission, not full statements.
- Some documents may need manual correction if the source is ambiguous or low quality.
- Image-based documents depend on OCR or vision support being available.
- Email handling in the MVP focuses on the email body rather than attachments.
- Duplicate detection warns the user, but the user remains responsible for the final decision.

## Assumptions

- AI assistance is optional and user-controlled.
- Self-hosted users manage their own provider access, storage, and operational setup.
- Review-before-save remains a deliberate product choice, not a temporary limitation.
- Email remains the notification channel for completion and failure in the MVP.

## Related Documentation

- Technical reference: [TECHNICAL.md](TECHNICAL.md)
- Future work and backlog: [FUTURE-IMPROVEMENTS.md](FUTURE-IMPROVEMENTS.md)

## Confidence Level

High
