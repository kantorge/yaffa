<?php

use Illuminate\Support\Facades\Facade;

return [

    'available_languages' => [
        'en' => 'English',
        'hu' => 'Hungarian',
    ],

    'available_locales' => [
        'en-US' => 'English (United States)',
        'hu-HU' => 'Hungarian',
    ],


    'aliases' => Facade::defaultAliases()->merge([
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
