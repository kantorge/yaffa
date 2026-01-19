<?php

use Illuminate\Support\Facades\Facade;

return [

    'available_languages' => [
        'en' => 'English',
        'hu' => 'Hungarian',
        'fr' => 'French',
    ],

    'available_locales' => [
        'en-US' => 'English (United States)',
        'hu-HU' => 'Hungarian',
        'fr-FR' => 'French (France)',
    ],


    'aliases' => Facade::defaultAliases()->merge([
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
