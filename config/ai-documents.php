<?php

return [
    /*
     * File upload configuration
     */
    'file_upload' => [
        'max_files_per_submission' => env('AI_DOCUMENT_MAX_FILES_PER_SUBMISSION', 10),
        'max_file_size_mb' => env('AI_DOCUMENT_MAX_FILE_SIZE_MB', 50),
        'max_total_size_mb' => env('AI_DOCUMENT_MAX_TOTAL_SIZE_MB', 500),
        'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'txt'],
    ],

    /*
     * File retention and cleanup
     */
    'file_retention' => [
        'retention_days' => env('AI_DOCUMENT_FILE_RETENTION_DAYS', 90),
    ],

    /*
     * Processing configuration
     */
    'processing' => [
        'max_retries' => 3,
        'retry_delay_seconds' => 30,
        'processing_timeout_seconds' => 300,
        'ai_temperature' => 0.1,
        'ai_top_p' => 1,
        'ai_frequency_penalty' => 0,
        'ai_presence_penalty' => 0,
    ],

    /*
     * Asset matching similarity threshold
     */
    'asset_matching' => [
        'similarity_threshold' => 0.5,
        'max_suggestions' => 10,
    ],

    /*
     * Duplicate detection configuration
     */
    'duplicate_detection' => [
        'date_window_days' => 3,
        'amount_tolerance_percent' => 10,
        'similarity_threshold' => 0.5,
    ],

    /*
     * Google Drive monitoring configuration
     */
    'google_drive' => [
        'enabled' => env('AI_GOOGLE_DRIVE_ENABLED', false),
        'sync_interval_minutes' => env('AI_GOOGLE_DRIVE_SYNC_INTERVAL_MINUTES', 15),
        'scopes' => [
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/drive.readonly',
        ],
    ],

    /*
     * Supported AI providers and models
     */
    'providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'models' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-3.5-turbo-instruct',
            ],
            'default_model' => 'gpt-4o-mini',
        ],
        'gemini' => [
            'name' => 'Google Gemini',
            'models' => [
                'gemini-1.5-pro',
                'gemini-1.5-flash',
            ],
            'default_model' => 'gemini-1.5-flash',
        ],
    ],

    /*
     * Image processing
     */
    'image_processing' => [
        'max_width' => 2048,
        'max_height' => 2048,
        'quality' => 85,
    ],
];
