<?php

return [
    // Read the version from the composer.json file. This is used in the footer, but has no other purpose.
    'version' => json_decode(file_get_contents(base_path('composer.json')))->version,

    'admin_email' => env('ADMIN_EMAIL', 'admin@yaffa.test'),
    'alpha_vantage_key' => env('ALPHA_VANTAGE_KEY'),
    'registered_user_limit' => intval(env('REGISTERED_USER_LIMIT')),
    'incoming_receipts_email' => env('INCOMING_RECEIPTS_EMAIL'),
    'email_verification_required' => env('EMAIL_VERIFICATION_REQUIRED', false),

    // Optional settings, used primarily for the public facing Sandbox environment
    'sandbox_mode' => env('SANDBOX_MODE', false),
    'gtm_container_id' => env('GTM_CONTAINER_ID'),
    'cookieyes_id' => env('COOKIEYES_ID'),

    // These are not actual config values, but a list of supported date presets for account details.
    // The default / empty value is not added, as it behaves differently in various places.
    // Translations for the labels are handled in the view.
    // The actual behavior is also imlemented in the frontend (Vue.js).
    'account_date_presets' => [
        [
            'label' => 'Current interval',
            'options' => [
                ['value' => 'thisMonth', 'label' => 'This month'],
                ['value' => 'thisQuarter', 'label' => 'This quarter'],
                ['value' => 'thisYear', 'label' => 'This year'],
                ['value' => 'thisMonthToDate', 'label' => 'This month to date'],
                ['value' => 'thisQuarterToDate', 'label' => 'This quarter to date'],
                ['value' => 'thisYearToDate', 'label' => 'This year to date'],
            ],
        ],
        [
            'label' => 'Previous day(s)',
            'options' => [
                ['value' => 'yesterday', 'label' => 'Yesterday'],
                ['value' => 'previous7Days', 'label' => 'Previous 7 days'],
                ['value' => 'previous30Days', 'label' => 'Previous 30 days'],
                ['value' => 'previous90Days', 'label' => 'Previous 90 days'],
                ['value' => 'previous180Days', 'label' => 'Previous 180 days'],
                ['value' => 'previous365Days', 'label' => 'Previous 365 days'],
            ],
        ],
        [
            'label' => 'Previous interval',
            'options' => [
                ['value' => 'previousMonth', 'label' => 'Previous month'],
                ['value' => 'previousMonthToDate', 'label' => 'Previous month to date'],
            ],
        ]
    ]
];
