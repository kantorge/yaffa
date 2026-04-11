<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
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

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($this->user)
            ->withdrawal($this->user)
            ->create();

        // The factory computes currency_id from account config; clear it to test the fallback accessor path.
        $transaction->forceFill(['currency_id' => null])->saveQuietly();
        $transaction->refresh();
        $transaction->unsetRelation('currency');

        $this->assertNull($transaction->currency);
        $this->assertSame($baseCurrency->id, $transaction->transaction_currency?->id);
    }
}
