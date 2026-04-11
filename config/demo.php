<?php

return [
    'ai_provider' => env('DEMO_AI_PROVIDER'),
    'ai_model' => env('DEMO_AI_MODEL'),
    'ai_api_key' => env('DEMO_AI_API_KEY'),
    'investment_provider_key' => env('DEMO_INVESTMENT_PROVIDER_KEY'),
    'investment_provider_api_key' => env('DEMO_INVESTMENT_PROVIDER_API_KEY'),
    'investment_provider_credentials' => env('DEMO_INVESTMENT_PROVIDER_CREDENTIALS'),
    'investment_provider_enabled' => env('DEMO_INVESTMENT_PROVIDER_ENABLED', true),
    'google_drive_folder_id' => env('DEMO_GOOGLE_DRIVE_FOLDER_ID'),
    'google_drive_json_key_file' => env('DEMO_GOOGLE_DRIVE_JSON_KEY_FILE'),
];
