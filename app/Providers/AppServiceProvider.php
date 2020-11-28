<?php

namespace App\Providers;

use App\Account;
use App\Payee;
use App\TransactionDetailStandard;
use App\TransactionDetailInvestment;
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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        require_once __DIR__ . '/../Http/Helpers.php';

        Schema::defaultStringLength(191);

        Relation::morphMap([
            'account' => Account::class,
            'payee' => Payee::class,
            'transaction_detail_standard' => TransactionDetailStandard::class,
            'transaction_detail_investment' => TransactionDetailInvestment::class,
        ]);

        Blade::directive('NiceNumber', function ($expression) {
            return "<?php echo str_replace(' ', '&nbsp;', number_format(intval($expression), 0, ',', ' ')); ?>";
        });
    }
}
