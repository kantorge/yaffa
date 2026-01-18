<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionCurrencyTest extends TestCase
{
    protected static bool $migrationRun = false;

    protected const string USER_EMAIL = 'demo@yaffa.cc';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }

        $this->user = User::where('email', static::USER_EMAIL)->firstOrFail();

        Cache::flush();
    }

    public function test_transaction_currency_falls_back_to_base_when_null(): void
    {
        /** @var Currency $baseCurrency */
        $baseCurrency = $this->user->currencies()->where('base', true)->first();

        // Get key assets of the already selected test user
        $account = $this->user->accounts()->with('config')->first();
        $payee = $this->user->payees()->first();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($this->user)
            ->for(TransactionDetailStandard::factory()->create([
                'account_from_id' => $account->id,
                'account_to_id' => $payee->id,
                'amount_from' => 1000,
                'amount_to' => 1000,
            ]),
            'config')
            ->create([
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'currency_id' => null,
            ]);

        // Should return base currency
        $this->assertEquals($baseCurrency->id, $transaction->currency->id);
    }
}
