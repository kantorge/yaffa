<?php

// TODO: majority of these items could be configured in the UI
return [
    'admin_email' => env('ADMIN_EMAIL'),
    'alpha_vantage_key' => env('ALPHA_VANTAGE_KEY'),
    'registered_user_limit' => env('REGISTERED_USER_LIMIT', null),
    'app_start_date' => env('APP_START_DATE', '2007-01-01'),
    'app_end_date' => env('APP_END_DATE', '2070-12-31'),
];
