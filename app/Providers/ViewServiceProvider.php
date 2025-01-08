<?php

namespace App\Providers;

use App\Http\View\Composers\AccountGroupListComposer;
use App\Http\View\Composers\AccountListComposer;
use App\Http\View\Composers\CategoryListComposer;
use App\Http\View\Composers\CategoryParentListComposer;
use App\Http\View\Composers\CurrencyListComposer;
use App\Http\View\Composers\DataLayerEventComposer;
use App\Http\View\Composers\InvestmentGroupListComposer;
use App\Http\View\Composers\InvestmentPriceProviderListComposer;
use App\Http\View\Composers\JavaScriptConfigVariablesComposer;
use App\Http\View\Composers\JavaScriptUserVariablesComposer;
use App\Http\View\Composers\NotificationMessageComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // General notification helper content; used in all layouts
        View::composer('template.layouts.page', NotificationMessageComposer::class);
        View::composer('template.layouts.auth', NotificationMessageComposer::class);

        // DataLayer for Google Tag Manager
        View::composer('template.layouts.page', DataLayerEventComposer::class);
        View::composer('template.layouts.auth', DataLayerEventComposer::class);

        // Below composers are for logged in users only, based on view files

        // Account list for quick jump
        View::composer('template.layouts.page', AccountListComposer::class);

        // Generic JavaScript variables
        View::composer('template.layouts.page', JavaScriptConfigVariablesComposer::class);
        View::composer('template.layouts.auth', JavaScriptConfigVariablesComposer::class);

        // User-related JavaScript variables
        View::composer('template.layouts.page', JavaScriptUserVariablesComposer::class);

        // Account form - all account groups
        View::composer('account.form', AccountGroupListComposer::class);

        // Investment form - all investment groups
        View::composer('investment.form', InvestmentGroupListComposer::class);

        // Investment form / Account form - all currencies
        View::composer('investment.form', CurrencyListComposer::class);
        View::composer('account.form', CurrencyListComposer::class);

        // Investment form - all price providers
        View::composer('investment.form', InvestmentPriceProviderListComposer::class);

        // Category parent list for category forms
        View::composer('categories.form', CategoryParentListComposer::class);

        // All categories for payee form
        View::composer('payee.form', CategoryListComposer::class);
    }
}
