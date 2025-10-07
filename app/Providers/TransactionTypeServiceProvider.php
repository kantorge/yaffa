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
     * Get the transaction types in the necessary format.
     */
    private function getTransactionTypes(): array
    {
        return TransactionType::all()->keyBy('name')->toArray();
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
                    fn() => $this->getTransactionTypes()
                );
            } catch (Exception $e) {
                $transactionTypeData = [];
            }
            config()->set('transaction_types', $transactionTypeData ?? []);
        } else {
            // When running in console, do not use caching, but the value is still needed
            config()->set('transaction_types', $this->getTransactionTypes());
        }
    }
}
