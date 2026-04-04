<?php

namespace App\Providers;

use App\Components\MailHandler;
use App\Jobs\GetInvestmentPrices;
use App\Models\Account;
use App\Models\Payee;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Services\InvestmentPriceProviderContextResolver;
use BeyondCode\Mailbox\Facades\Mailbox;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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
            $router = $this->app->make(Router::class);
            $router->middlewareGroup('api', [
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

            return $this->app->environment('production')
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

        RateLimiter::for('investment-price-provider', function (object $job) {
            if (! $job instanceof GetInvestmentPrices) {
                return Limit::perMinute(10000)->by('investment-price-provider:default');
            }

            $policy = $job->getRateLimitPolicy(app(InvestmentPriceProviderContextResolver::class));
            $bucketKey = isset($policy['bucketKey']) && is_string($policy['bucketKey'])
                ? $policy['bucketKey']
                : 'investment-price-provider:default';

            $limits = [];

            if (isset($policy['perSecond']) && is_numeric($policy['perSecond'])) {
                $limits[] = Limit::perMinute(max(1, (int) $policy['perSecond'] * 60))->by($bucketKey . ':second');
            }

            if (isset($policy['perMinute']) && is_numeric($policy['perMinute'])) {
                $limits[] = Limit::perMinute(max(1, (int) $policy['perMinute']))->by($bucketKey . ':minute');
            }

            if (isset($policy['perDay']) && is_numeric($policy['perDay'])) {
                $limits[] = Limit::perDay(max(1, (int) $policy['perDay']))->by($bucketKey . ':day');
            }

            if ($limits === []) {
                return Limit::perMinute(10000)->by($bucketKey . ':fallback');
            }

            return $limits;
        });

        $this->bootEvent();
    }

    public function bootEvent(): void
    {

    }
}
