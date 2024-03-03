<?php

namespace App\Providers;

use App\Components\MailHandler;
use App\Models\Account;
use App\Models\Payee;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use BeyondCode\Mailbox\Facades\Mailbox;
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
        // Register Telescope if enabled
        if ($this->app->environment('local') && config('telescope.enabled')) {
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
        //Model::preventLazyLoading(! app()->isProduction());

        Schema::defaultStringLength(191);

        Relation::morphMap([
            'account' => Account::class,
            'payee' => Payee::class,
            'standard' => TransactionDetailStandard::class,
            'investment' => TransactionDetailInvestment::class,
        ]);

        // Setup Mailbox to handle incoming emails sent to specified address, if this email address is configured
        if (config('yaffa.incoming_receipts_email')) {
            Mailbox::to(config('yaffa.incoming_receipts_email'), MailHandler::class);
        }
    }
}
