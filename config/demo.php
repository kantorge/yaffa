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

    // The latest date present in database/seeders/demo.sql. Reset shifts dates forward from
    // this anchor to "now"; dumping/promoting shifts them back to this anchor before export.
    'seed_anchor_date' => '2008-12-31',

    // Tables/columns in demo.sql whose dates are relative to seed_anchor_date and must be
    // shifted in lockstep by both app:sandbox:reset-database and the sandbox dump/promote commands.
    // Note: investment_prices is intentionally NOT included here - its seed dates are already
    // authored close to "now" (not relative to seed_anchor_date like the tables below), so shifting
    // it by the same offset would push prices into the future. It is still included in the dump/
    // promote table list so manual edits to it are captured, just without a date shift applied.
    'seed_date_shift_columns' => [
        'transactions' => ['columns' => ['date'], 'scope' => ['user_id' => 1]],
        'transaction_schedules' => ['columns' => ['start_date', 'next_date', 'end_date'], 'scope' => null],
    ],

    // Tables where reset-database's own post-load steps (not the demo.sql load itself) create
    // rows through Eloquent - e.g. the investment price-fetch job, or the sample received-mail
    // simulation. Eloquent always sets created_at; raw-loaded seed rows never do. So exporting
    // only rows with created_at IS NULL keeps hand-curated/seed rows while dropping anything
    // regenerated live, without needing a dedicated "is this a seed row" flag.
    'seed_only_tables' => [
        'investment_prices',
        'received_mails',
    ],
];
