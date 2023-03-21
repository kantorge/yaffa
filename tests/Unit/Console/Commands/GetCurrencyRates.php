<?php

namespace Tests\Unit\Console\Commands;

use App\Jobs\GetCurrencyRates as GetCurrencyRatesJob;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GetCurrencyRates extends TestCase
{
    use RefreshDatabase;

    private const COMMAND_SIGNATURE = 'app:currency-rates:get';

    /**
     * Base currency is not queued for currency rate retrieval.
     *
     * @test
     */
    public function base_currency_is_not_queued_for_currency_rate_retrieval()
    {
        $user = User::factory()->create();
        Currency::factory()->create([
            'user_id' => $user->id,
            'base' => true,
            'auto_update' => true,
        ]);

        // Prevent actual job processing
        $queue = Queue::fake();

        $this->artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        // Assert that the job was not queued
        $queue->assertNotPushed(GetCurrencyRatesJob::class);
    }

    /**
     * Currency rate retrieval is not queued for currencies with auto_update set to false.
     *
     * @test
     */
    public function currency_rate_retrieval_is_not_queued_for_currencies_with_auto_update_set_to_false()
    {
        $user = User::factory()->create();
        Currency::factory()->create([
            'user_id' => $user->id,
            'base' => null,
            'auto_update' => false,
        ]);

        // Prevent actual job processing
        $queue = Queue::fake();

        $this->artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        // Assert that the job was not queued
        $queue->assertNotPushed(GetCurrencyRatesJob::class);
    }

    /**
     * Currency rate retrieval is queued for currencies with auto_update set to true.
     *
     * @test
     */
    public function currency_rate_retrieval_is_queued_for_currencies_with_auto_update_set_to_true()
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create([
            'user_id' => $user->id,
            'base' => null,
            'auto_update' => true,
        ]);

        // Prevent actual job processing
        $queue = Queue::fake();

        $this->artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        // Assert that the job was queued
        $queue->assertPushed(GetCurrencyRatesJob::class, fn ($job) => $job->currency->id === $currency->id);
    }
}
