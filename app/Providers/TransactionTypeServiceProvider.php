<?php

namespace App\Providers;

use App\Models\TransactionType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class TransactionTypeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Empty
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $transactionTypeData = Cache::remember(
            'transaction_types',
            60 * 60 * 24 * 30,
            fn () => TransactionType::all()->keyBy('name')->toArray()
        );

        config()->set('transaction_types', $transactionTypeData);
    }
}
