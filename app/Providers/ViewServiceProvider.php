<?php

namespace App\Providers;

use App\Http\View\Composers\AccountListComposer;
use App\Http\View\Composers\CurrencyListComposer;
use App\Http\View\Composers\InvestmentGroupListComposer;
use App\Http\View\Composers\InvestmentPriceProviderListComposer;
use App\Http\View\Composers\NotificationMessageComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Account list for quick jump
        View::composer('*', AccountListComposer::class);

        // General notification helper content
        View::composer('*', NotificationMessageComposer::class);

        // Investment form - all investment groups
        View::composer('investment.form', InvestmentGroupListComposer::class);

        // Investment form - all currencies
        View::composer('investment.form', CurrencyListComposer::class);

        // Investment form - all price providers
        View::composer('investment.form', InvestmentPriceProviderListComposer::class);
    }
}
