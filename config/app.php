<?php

use Illuminate\Support\Facades\Facade;

return [

    'available_languages' => [
        'en' => 'English',
        'fr' => 'French',
        'hu' => 'Hungarian',
        'pl' => 'Polish',
    ],

    'available_locales' => [
        'en-US' => 'English (United States)',
        'fr-FR' => 'French (France)',
        'hu-HU' => 'Hungarian',
        'pl-PL' => 'Polish',
    ],


    'aliases' => Facade::defaultAliases()->merge([
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
