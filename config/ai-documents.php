<?php

return [
    /*
     * File upload configuration
     */
    'file_upload' => [
        'max_files_per_submission' => env('AI_DOCUMENT_MAX_FILES_PER_SUBMISSION', 5),
        'max_file_size_mb' => env('AI_DOCUMENT_MAX_FILE_SIZE_MB', 20),
        // This is a restrictive default. Adjust as needed, but be cautious about allowing too many types for security reasons.
        'allowed_types' => explode(',', env('AI_DOCUMENT_ALLOWED_TYPES', 'txt'))
    ],

    /*
     * File retention and cleanup - NOT IMPLEMENTED YET, but reserved for future use.
     */
    'local_storage_file_retention' => [
        'retention_days' => env('AI_DOCUMENT_FILE_RETENTION_DAYS', 90),
    ],

    /*
     * Supported AI providers and models
     */
    'providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'supported' => true,
            'models' => [
                'gpt-4o-mini' => [
                    'vision' => true,
                    'supported' => true,
                ],
                'gpt-4o' => [
                    'vision' => true,
                    'supported' => true,
                ],
            ],
        ],
        'gemini' => [
            'name' => 'Google Gemini',
            'supported' => true,
            'models' => [
                'gemini-2.5-flash' => [
                    'vision' => true,
                    'supported' => true,
                ],
                'gemini-2.5-pro' => [
                    'vision' => true,
                    'supported' => true,
                ],
            ],
        ],
    ],

    /*
     * OCR configuration
     */
    'ocr' => [
        'tesseract_enabled' => env('TESSERACT_ENABLED', false),
        'tesseract_mode' => env('TESSERACT_MODE', 'binary'), // 'binary' or 'http'

        // Binary mode (local execution on same container)
        'tesseract_binary' => [
            'path' => env('TESSERACT_PATH', '/usr/bin/tesseract'),
        ],

        // HTTP mode (separate Tesseract container/service)
        'tesseract_http' => [
            'host' => env('TESSERACT_HTTP_HOST', 'localhost'),
            'port' => env('TESSERACT_HTTP_PORT', 8888),
            'timeout' => env('TESSERACT_HTTP_TIMEOUT', 30),
            'endpoint' => '/api/v1/ocr', // Path to OCR endpoint on HTTP server
        ],
    ],

];
