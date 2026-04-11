<?php

return [
    "ai_document_processed" => [
        "button_open_documents" => "Open AI Documents",
        "button_review_document" => "Review Document",
        "extracted_summary" => "Extracted summary",
        "intro" => "Your AI document is ready for review.",
        "next_action_text" => "Review the extracted values, adjust anything needed, then finalize the transaction.",
        "next_action_title" => "Next action",
        "subject" => "Document Processed - Ready for Review",
        "what_happened" => "What happened"
    ],
    "ai_document_processing_failed" => [
        "button_open_settings" => "Open AI Settings",
        "button_review_reprocess" => "Review & Reprocess",
        "document_details" => "Document details",
        "fallback_reason" => "An unknown error occurred during processing.",
        "intro" => "Your AI document could not be processed.",
        "next_action_text" => "Open the document, update the custom prompt if needed, and run reprocess again.",
        "next_action_title" => "Next action",
        "settings_hint" => "If this keeps failing, verify your AI provider model and credentials.",
        "subject" => "Document Processing Failed"
    ],
    "common" => [
        "greeting" => "Dear :name,",
        "na" => "N/A",
        "thanks" => "Thanks,"
    ],
    "google_drive_import_failed" => [
        "button_open_settings" => "Open Profile Settings",
        "error" => "Error: :error",
        "error_count" => "Consecutive errors: :count",
        "folder" => "Folder: :folder",
        "intro" => "The Google Drive import process failed.",
        "subject" => "Google Drive Import Failed"
    ],
    "google_drive_import_success" => [
        "button_open_documents" => "Open AI Documents",
        "failed_downloads" => "Download failures: :count",
        "folder" => "Folder: :folder",
        "imported" => "Imported documents: :count",
        "intro" => "Your Google Drive import has completed successfully.",
        "skipped_existing" => "Skipped (already imported): :count",
        "skipped_too_large" => "Skipped (too large): :count",
        "skipped_unsupported" => "Skipped (unsupported type): :count",
        "subject" => "Google Drive Import Completed"
    ],
    "labels" => [
        "amount" => "Amount",
        "date" => "Date",
        "document_id" => "Document ID",
        "processed" => "Processed",
        "reason" => "Reason",
        "source_type" => "Source",
        "submitted" => "Submitted",
        "type" => "Type"
    ],
    "source_types" => [
        "google_drive" => "Google Drive",
        "manual_upload" => "Manual upload",
        "received_email" => "Received email"
    ],
    "transaction_types" => [
        "add_shares" => "Add shares",
        "buy" => "Buy",
        "deposit" => "Deposit",
        "dividend" => "Dividend",
        "interest" => "Interest",
        "remove_shares" => "Remove shares",
        "sell" => "Sell",
        "transfer" => "Transfer",
        "withdrawal" => "Withdrawal"
    ]
];
