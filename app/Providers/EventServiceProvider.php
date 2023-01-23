<?php

namespace App\Providers;

use App\Events\Registered;
use App\Listeners\CreateDefaultAssetsForNewUser;
use App\Listeners\SendLoginFailedNotification;
use App\Listeners\SendLoginSuccessNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Failed::class => [
            SendLoginFailedNotification::class,
        ],

        Login::class => [
            SendLoginSuccessNotification::class,
        ],

        Registered::class => [
            CreateDefaultAssetsForNewUser::class,
            SendEmailVerificationNotification::class,
        ],
    ];
}
