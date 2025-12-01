<?php

return [
    // Read the version from the composer.json file. This is used in the footer, but has no other purpose.
    'version' => json_decode(file_get_contents(base_path('composer.json')))->version,

    'admin_email' => env('ADMIN_EMAIL', 'admin@yaffa.test'),
    'alpha_vantage_key' => env('ALPHA_VANTAGE_KEY'),
    'registered_user_limit' => intval(env('REGISTERED_USER_LIMIT')),
    'incoming_receipts_email' => env('INCOMING_RECEIPTS_EMAIL'),
    'email_verification_required' =>env('EMAIL_VERIFICATION_REQUIRED',false),

    // Gmail API settings for receiving emails via OAuth2
    'gmail' => [
        'enabled' => env('GMAIL_API_ENABLED', false),
        'client_id' => env('GMAIL_CLIENT_ID'),
        'client_secret' => env('GMAIL_CLIENT_SECRET'),
        'refresh_token' => env('GMAIL_REFRESH_TOKEN'),
        'user_email' => env('GMAIL_USER_EMAIL'),
        'whitelist' => array_filter(explode(',', env('GMAIL_SENDER_WHITELIST', ''))),
    ],

    // Optional settings, used primarily for the public facing Sandbox environment
    'sandbox_mode' => env('SANDBOX_MODE',false),
    'gtm_container_id' => env('GTM_CONTAINER_ID'),
    'cookieyes_id' => env('COOKIEYES_ID'),
];

