<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Payee;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Add throttling rule to the API routes, if running in production
        if ($this->app->environment('production')) {
            $this->app->router->middlewareGroup('api', [
                \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60:1',
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TODO: implement Model::preventLazyLoading(! app()->isProduction());

        Schema::defaultStringLength(191);

        Relation::morphMap([
            'account' => Account::class,
            'payee' => Payee::class,
            'transaction_detail_standard' => TransactionDetailStandard::class,
            'transaction_detail_investment' => TransactionDetailInvestment::class,
        ]);
    }
}
