<?php

namespace App\Providers;

use App\Events\IncomingEmailReceived;
use App\Events\Registered;
use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Listeners\CreateDefaultAssetsForNewUser;
use App\Listeners\DataLayerEventForLoginSuccess;
use App\Listeners\ProcessIncomingEmail;
use App\Listeners\ProcessTransactionCreated;
use App\Listeners\ProcessTransactionDeleted;
use App\Listeners\ProcessTransactionUpdated;
use App\Listeners\SendLoginFailedNotification;
use App\Listeners\SendLoginSuccessNotification;
use App\Models\CurrencyRate;
use App\Models\InvestmentPrice;
use App\Observers\CurrencyRateObserver;
use App\Observers\InvestmentPriceObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        Failed::class => [
            SendLoginFailedNotification::class,
        ],

        Login::class => [
            SendLoginSuccessNotification::class,
            DataLayerEventForLoginSuccess::class,
        ],

        Registered::class => [
            CreateDefaultAssetsForNewUser::class,
            SendEmailVerificationNotification::class,
        ],

        IncomingEmailReceived::class => [
            ProcessIncomingEmail::class,
        ],

        TransactionCreated::class => [
            ProcessTransactionCreated::class,
        ],

        TransactionUpdated::class => [
            ProcessTransactionUpdated::class,
        ],

        TransactionDeleted::class => [
            ProcessTransactionDeleted::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array<string, array<int, object|string>|object|string>
     */
    protected $observers = [
        CurrencyRate::class => [CurrencyRateObserver::class],
        InvestmentPrice::class => [InvestmentPriceObserver::class],
    ];
}
