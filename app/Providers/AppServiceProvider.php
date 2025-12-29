<?php

namespace App\Providers;

use App\Components\MailHandler;
use App\Models\Account;
use App\Models\Payee;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use BeyondCode\Mailbox\Facades\Mailbox;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Telescope if enabled
        if (config('telescope.enabled')) {
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

        // Define the default password strength rules for the entire application
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : $rule;
        });

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

        // Manually override the default verification listener to use our own event which has a context parameter
        $this->app->bind(
            \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
            \App\Listeners\SendEmailVerificationNotification::class
        );

        $this->bootEvent();
    }

    public function bootEvent(): void
    {

    }
}
