<?php

return [
    'common' => [
        'na' => 'N/A',
        'greeting' => 'Dear :name,',
        'thanks' => 'Thanks,',
    ],

    'labels' => [
        'source_type' => 'Source',
        'submitted' => 'Submitted',
        'processed' => 'Processed',
        'document_id' => 'Document ID',
        'type' => 'Type',
        'amount' => 'Amount',
        'date' => 'Date',
        'reason' => 'Reason',
    ],

    'source_types' => [
        'manual_upload' => 'Manual upload',
        'received_email' => 'Received email',
        'google_drive' => 'Google Drive',
    ],

    'transaction_types' => [
        'withdrawal' => 'Withdrawal',
        'deposit' => 'Deposit',
        'transfer' => 'Transfer',
        'buy' => 'Buy',
        'sell' => 'Sell',
        'dividend' => 'Dividend',
        'interest' => 'Interest',
        'add_shares' => 'Add shares',
        'remove_shares' => 'Remove shares',
    ],

    'ai_document_processed' => [
        'subject' => 'Document Processed - Ready for Review',
        'intro' => 'Your AI document is ready for review.',
        'what_happened' => 'What happened',
        'extracted_summary' => 'Extracted summary',
        'next_action_title' => 'Next action',
        'next_action_text' => 'Review the extracted values, adjust anything needed, then finalize the transaction.',
        'button_review_document' => 'Review Document',
        'button_open_documents' => 'Open AI Documents',
    ],

    'ai_document_processing_failed' => [
        'subject' => 'Document Processing Failed',
        'intro' => 'Your AI document could not be processed.',
        'document_details' => 'Document details',
        'next_action_title' => 'Next action',
        'next_action_text' => 'Open the document, update the custom prompt if needed, and run reprocess again.',
        'settings_hint' => 'If this keeps failing, verify your AI provider model and credentials.',
        'fallback_reason' => 'An unknown error occurred during processing.',
        'button_review_reprocess' => 'Review & Reprocess',
        'button_open_settings' => 'Open AI Settings',
    ],

    'google_drive_import_success' => [
        'subject' => 'Google Drive Import Completed',
        'intro' => 'Your Google Drive import has completed successfully.',
        'folder' => 'Folder: :folder',
        'imported' => 'Imported documents: :count',
        'skipped_existing' => 'Skipped (already imported): :count',
        'skipped_unsupported' => 'Skipped (unsupported type): :count',
        'skipped_too_large' => 'Skipped (too large): :count',
        'failed_downloads' => 'Download failures: :count',
        'button_open_documents' => 'Open AI Documents',
    ],

    'google_drive_import_failed' => [
        'subject' => 'Google Drive Import Failed',
        'intro' => 'The Google Drive import process failed.',
        'folder' => 'Folder: :folder',
        'error' => 'Error: :error',
        'error_count' => 'Consecutive errors: :count',
        'button_open_settings' => 'Open Profile Settings',
    ],
];
