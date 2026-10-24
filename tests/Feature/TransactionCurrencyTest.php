<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_currency_falls_back_to_base_when_null(): void
    {
        Cache::flush();

        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()->for($user)->create(['base' => true]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal($user)
            ->create();

        // The factory computes currency_id from account config; clear it to test the fallback accessor path.
        $transaction->forceFill(['currency_id' => null])->saveQuietly();
        $transaction->refresh();
        $transaction->unsetRelation('currency');

        $this->assertNull($transaction->currency);
        $this->assertSame($baseCurrency->id, $transaction->transaction_currency?->id);
    }
}
