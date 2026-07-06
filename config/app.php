<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | YAFFA stores only calendar dates (no time-of-day values), so the timezone
    | setting mainly affects Carbon::today() / Carbon::now() in scheduled
    | commands. Set APP_TIMEZONE in .env to match the server's physical location
    | so that "today" resolves to the correct calendar date when automatic
    | transaction recording runs. Defaults to UTC.
    |
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'available_languages' => [
        'en' => 'English',
        'fr' => 'French',
        'hu' => 'Hungarian',
        'pl' => 'Polish',
    ],

    'available_locales' => [
        'en-GB' => 'English (United Kingdom)',
        'en-US' => 'English (United States)',
        'fr-FR' => 'French (France)',
        'hu-HU' => 'Hungarian',
        'pl-PL' => 'Polish',
    ],


    'aliases' => Facade::defaultAliases()->merge([
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
