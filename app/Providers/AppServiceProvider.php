<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Payee;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        Relation::morphMap([
            'account' => Account::class,
            'payee' => Payee::class,
            'transaction_detail_standard' => TransactionDetailStandard::class,
            'transaction_detail_investment' => TransactionDetailInvestment::class,
        ]);

        Blade::directive('NiceNumber', function ($expression) {
            return "<?php echo str_replace(' ', '&nbsp;', number_format(intval(${expression}), 0, ',', ' ')); ?>";
        });
    }
}
