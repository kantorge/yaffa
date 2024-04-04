<?php

namespace App\Providers;

use App\Models\TransactionType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Exception;

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
        if (!app()->runningInConsole()) {
            try {
                $transactionTypeData = Cache::remember(
                    'transaction_types',
                    60 * 60 * 24 * 30,
                    fn () => TransactionType::all()->keyBy('name')->toArray()
                );
            } catch (Exception $e) {
                $transactionTypeData = [];
            }
            config()->set('transaction_types', $transactionTypeData ?? []);
        }
    }
}
