<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'available_languages' => [
        'en' => 'English',
        'hu' => 'Hungarian',
    ],

    'available_locales' => [
        'en-US' => 'English (United States)',
        'hu-HU' => 'Hungarian',
    ],

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Laravel Framework Service Providers...
         */
        //Illuminate\Pagination\PaginationServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\FakerServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        //App\Providers\TelescopeServiceProvider::class,
        App\Providers\ViewServiceProvider::class,
        App\Providers\TransactionTypeServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
