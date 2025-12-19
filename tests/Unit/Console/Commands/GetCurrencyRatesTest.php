<?php

namespace Tests\Unit\Console\Commands;

use App\Jobs\GetCurrencyRates as GetCurrencyRatesJob;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GetCurrencyRatesTest extends TestCase
{
    use RefreshDatabase;

    private const string COMMAND_SIGNATURE = 'app:currency-rates:get';

    /**
     * Base currency is not queued for currency rate retrieval.
     */
    public function test_base_currency_is_not_queued_for_currency_rate_retrieval(): void
    {
        // Ensure that the database is empty
        $this->artisan('migrate:fresh');

        /** @var User $user */
        $user = User::factory()->create();

        Currency::factory()
            ->for($user)
            ->create([
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
     */
    public function test_currency_rate_retrieval_is_not_queued_for_currencies_with_auto_update_set_to_false(): void
    {
        // Ensure that the database is empty
        $this->artisan('migrate:fresh');

        /** @var User $user */
        $user = User::factory()->create();

        Currency::factory()
            ->for($user)
            ->create([
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
     */
    public function test_currency_rate_retrieval_is_queued_for_currencies_with_auto_update_set_to_true(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $currency */
        $currency = Currency::factory()
            ->for($user)
            ->create([
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
