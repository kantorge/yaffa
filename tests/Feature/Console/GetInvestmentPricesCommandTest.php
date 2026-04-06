<?php

namespace Tests\Feature\Console;

use App\Jobs\GetInvestmentPrices as GetInvestmentPricesJob;
use App\Models\Investment;
use App\Models\InvestmentProviderConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GetInvestmentPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_jobs_with_grouped_provider_policy_payload(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'web_scraping',
            'provider_settings' => [
                'url' => 'https://example.com/price-1',
                'selector' => '.price',
            ],
        ]);

        Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'web_scraping',
            'provider_settings' => [
                'url' => 'https://example.com/price-2',
                'selector' => '.price',
            ],
        ]);

        $this->artisan('app:investment-prices:get')->assertSuccessful();

        Queue::assertPushed(GetInvestmentPricesJob::class, 2);
        Queue::assertPushed(GetInvestmentPricesJob::class, fn (GetInvestmentPricesJob $job): bool => is_array($job->rateLimitPolicy)
                && isset($job->rateLimitPolicy['bucketKey'])
                && str_contains((string) $job->rateLimitPolicy['bucketKey'], 'investment-price-provider:'));
    }

    public function test_budget_capping_prevents_dispatch_when_daily_budget_is_exhausted(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        InvestmentProviderConfig::factory()->create([
            'user_id' => $user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => ['api_key' => 'alpha-test-key-123456'],
            'rate_limit_overrides' => [
                'perDay' => 2,
            ],
        ]);

        Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'alpha_vantage',
            'last_price_fetch_attempted_at' => Carbon::today()->setHour(1),
        ]);

        Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'alpha_vantage',
            'last_price_fetch_attempted_at' => Carbon::today()->setHour(2),
        ]);

        Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'alpha_vantage',
            'last_price_fetch_attempted_at' => null,
        ]);

        $this->artisan('app:investment-prices:get')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_preflight_failure_for_missing_credentials_marks_fetch_state_and_skips_dispatch(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $investment = Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'alpha_vantage',
        ]);

        $this->artisan('app:investment-prices:get')->assertSuccessful();

        Queue::assertNothingPushed();

        $investment->refresh();
        $this->assertNotNull($investment->last_price_fetch_error_at);
        $this->assertNotNull($investment->last_price_fetch_error_message);
        $this->assertStringContainsString('Missing required provider credentials', (string) $investment->last_price_fetch_error_message);
    }

    public function test_preflight_failure_for_missing_provider_settings_marks_fetch_state_and_skips_dispatch(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $investment = Investment::factory()->withUser($user)->create([
            'auto_update' => true,
            'active' => true,
            'investment_price_provider' => 'web_scraping',
            'provider_settings' => null,
        ]);

        $this->artisan('app:investment-prices:get')->assertSuccessful();

        Queue::assertNothingPushed();

        $investment->refresh();
        $this->assertNotNull($investment->last_price_fetch_error_at);
        $this->assertNotNull($investment->last_price_fetch_error_message);
        $this->assertStringContainsString('Missing required investment provider setting', (string) $investment->last_price_fetch_error_message);
    }
}
