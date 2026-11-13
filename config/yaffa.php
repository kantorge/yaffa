<?php

// Read the version from the composer.json file. This is used in the footer, but has no other purpose.
$version = json_decode(file_get_contents(base_path('composer.json')))->version;

// deploy.php writes the deployed git ref (tag/branch) to BUILD_REF, and Deployer's own
// deploy:update_code task writes the exact commit SHA to REVISION. Neither file exists
// outside a deployed release (e.g. local dev), so 'build' is null there.
// When the deployed ref is the official version tag, the version number alone is enough,
// so 'build' stays null and nothing extra is shown.
$build = null;
if (file_exists(base_path('BUILD_REF'))) {
    $ref = mb_trim(file_get_contents(base_path('BUILD_REF')));
    if ($ref !== $version && $ref !== "v{$version}") {
        $build = $ref;
        if (file_exists(base_path('REVISION'))) {
            $build .= '@' . mb_substr(mb_trim(file_get_contents(base_path('REVISION'))), 0, 7);
        }
    }
}

return [
    'version' => $version,
    // Non-null only for a deployed release whose ref doesn't match the version tag
    // (e.g. a manual deploy of an unreleased branch) — see comment above.
    'build' => $build,

    'admin_email' => env('ADMIN_EMAIL', 'admin@yaffa.test'),
    'registered_user_limit' => intval(env('REGISTERED_USER_LIMIT')),
    'incoming_receipts_email' => env('INCOMING_RECEIPTS_EMAIL'),
    'email_verification_required' => env('EMAIL_VERIFICATION_REQUIRED', false),
    'runs_scheduler' => env('RUNS_SCHEDULER', false),
    'import_max_file_size_mb' => (int) env('IMPORT_MAX_FILE_SIZE_MB', 2),
    'import_max_rows' => (int) env('IMPORT_MAX_ROWS', 5000),
    'api_token_max_lifetime_days' => (int) env('API_TOKEN_MAX_LIFETIME_DAYS', 365),
    'mobile' => [
        'min_app_version' => env('MOBILE_APP_MIN_VERSION', '0.0.0'),
    ],
    'idempotency_retention_days' => (int) env('IDEMPOTENCY_RETENTION_DAYS', 7),

    // Budget drift review thresholds (see .ai/docs/specifications/budget-drift-suggestions/).
    // Instance-wide and tuned in config only; sensitivity and bias are the user-facing run options.
    'budget_suggestions' => [
        'default_sensitivity' => 'medium',   // low | medium | high
        'default_bias' => 'neutral',          // neutral | conservative
        'min_history' => ['month' => 6, 'quarter' => 4, 'year' => 3],
        'level_window' => ['month' => 12, 'quarter' => 4, 'year' => 5], // = chart moving average
        'horizon' => ['month' => 12, 'quarter' => 4, 'year' => 2],
        'cooldown' => ['month' => 3, 'quarter' => 2, 'year' => 1],
        'sensitivity' => [
            'low' => ['tolerance' => 0.20, 'persistence' => ['month' => [4, 4], 'quarter' => [2, 3], 'year' => [2, 2]]],
            'medium' => ['tolerance' => 0.10, 'persistence' => ['month' => [3, 4], 'quarter' => [2, 3], 'year' => [2, 2]]],
            'high' => ['tolerance' => 0.05, 'persistence' => ['month' => [2, 3], 'quarter' => [2, 3], 'year' => [2, 2]]],
        ],
        'materiality_floor_share' => 0.0,   // share of average monthly expenses; 0 = off; for a future scheduled-notification mode
        'bias' => ['unfavorable_factor' => 0.75, 'favorable_factor' => 1.5, 'margin' => 0.03],
        'neutral_net_share' => 0.25,
        'regularity' => ['month' => [10, 12], 'quarter' => [3, 4], 'year' => [2, 3]], // [k, of n] periods with at least one transaction
        'max_sync_seconds' => 5,
    ],
    'scramble_prod_auth' => env('SCRAMBLE_PROD_AUTH', 'none'),

    // Optional settings, used primarily for the public facing Sandbox environment
    'sandbox_mode' => env('SANDBOX_MODE', false),
    'gtm_container_id' => env('GTM_CONTAINER_ID'),

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
