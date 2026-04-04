<?php

namespace Tests\Unit\Services;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\PriceProviderException;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\User;
use App\Services\InvestmentPriceProviderRegistry;
use App\Services\InvestmentPriceProviderContextResolver;
use App\Services\InvestmentProviderRateLimitPolicyResolver;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class InvestmentServicePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_investment_successfully_when_not_in_use(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $result = $service->delete($investment);

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
        $this->assertDatabaseMissing('investments', ['id' => $investment->id]);
    }

    public function test_delete_investment_fails_when_in_use(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(), 'config')
            ->create();

        TransactionDetailInvestment::factory()
            ->for($investment)
            ->for($account, 'account')
            ->create();

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $result = $service->delete($investment);

        $this->assertFalse($result['success']);
        $this->assertSame(__('Investment is in use, cannot be deleted'), $result['error']);
        $this->assertDatabaseHas('investments', ['id' => $investment->id]);
    }

    private function createMockProvider(array $prices = []): InvestmentPriceProvider
    {
        $provider = Mockery::mock(InvestmentPriceProvider::class);
        $provider->shouldReceive('fetchPrices')->andReturn($prices);
        $provider->shouldReceive('getName')->andReturn('mock_provider');
        $provider->shouldReceive('getDisplayName')->andReturn('Mock provider');
        $provider->shouldReceive('getDescription')->andReturn('Mock provider description');
        $provider->shouldReceive('getInstructions')->andReturn('Mock provider instructions');
        $provider->shouldReceive('getInvestmentSettingsSchema')->andReturn([
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ]);
        $provider->shouldReceive('getUserSettingsSchema')->andReturn([
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ]);
        $provider->shouldReceive('getRateLimitPolicy')->andReturn([
            'perSecond' => null,
            'perMinute' => null,
            'perDay' => null,
            'reserve' => 0,
            'overrideable' => false,
        ]);
        $provider->shouldReceive('supportsHistoricalSync')->andReturn(true);

        return $provider;
    }

    private function createService(InvestmentPriceProviderRegistry $registry): InvestmentService
    {
        $policyResolver = new InvestmentProviderRateLimitPolicyResolver();
        $contextResolver = new InvestmentPriceProviderContextResolver($registry, $policyResolver);

        return new InvestmentService($contextResolver);
    }

    public function test_fetch_and_save_prices_calls_provider(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create([
            'investment_price_provider' => 'mock_provider',
        ]);

        $prices = [
            ['date' => '2024-01-15', 'price' => 150.25],
            ['date' => '2024-01-14', 'price' => 149.50],
        ];

        $provider = $this->createMockProvider($prices);

        $registry = new InvestmentPriceProviderRegistry();
        $registry->register('mock_provider', $provider);

        $service = $this->createService($registry);
        $result = $service->fetchAndSavePrices($investment);

        $this->assertCount(2, $result);
        $this->assertEquals('2024-01-15', $result[0]['date']);
    }

    public function test_fetch_and_save_prices_persists_to_database(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create([
            'investment_price_provider' => 'mock_provider',
        ]);

        $prices = [
            ['date' => '2024-01-15', 'price' => 150.25],
            ['date' => '2024-01-14', 'price' => 149.50],
        ];

        $provider = $this->createMockProvider($prices);

        $registry = new InvestmentPriceProviderRegistry();
        $registry->register('mock_provider', $provider);

        $service = $this->createService($registry);
        $service->fetchAndSavePrices($investment);

        $this->assertDatabaseHas('investment_prices', [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => 150.25,
        ]);

        $this->assertDatabaseHas('investment_prices', [
            'investment_id' => $investment->id,
            'date' => '2024-01-14',
            'price' => 149.50,
        ]);
    }

    public function test_fetch_and_save_prices_throws_for_unknown_provider(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create([
            'investment_price_provider' => 'unknown_provider',
        ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('unknown provider');

        $service->fetchAndSavePrices($investment);
    }

    public function test_fetch_and_save_prices_throws_when_no_provider_configured(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create([
            'investment_price_provider' => null,
        ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('no price provider configured');

        $service->fetchAndSavePrices($investment);
    }

    public function test_get_current_quantity_without_account(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();
        $account = AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user)->create(), 'config')->create();

        // Create transactions
        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account, 'account')->state(['quantity' => 10]), 'config')
            ->create([
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account, 'account')->state(['quantity' => 5]), 'config')
            ->create([
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $quantity = $service->getCurrentQuantity($investment);

        $this->assertEquals(15, $quantity);
    }

    public function test_get_current_quantity_with_account(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();
        $account1 = AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user)->create(), 'config')->create();
        $account2 = AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user)->create(), 'config')->create();

        // Create transactions in different accounts
        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account1, 'account')->state(['quantity' => 10]), 'config')
            ->create([
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account2, 'account')->state(['quantity' => 5]), 'config')
            ->create([
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $quantity = $service->getCurrentQuantity($investment, $account1);

        $this->assertEquals(10, $quantity);
    }

    public function test_get_latest_price_type_stored(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();

        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-15',
            'price' => 150.25,
        ]);

        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-14',
            'price' => 149.50,
        ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $price = $service->getLatestPrice($investment, 'stored');

        $this->assertEquals(150.25, $price);
    }

    public function test_get_latest_price_type_transaction(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();
        $account = AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user)->create(), 'config')->create();

        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account, 'account')->state(['price' => 148.75]), 'config')
            ->create([
                'date' => '2024-01-15',
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $price = $service->getLatestPrice($investment, 'transaction');

        $this->assertEquals(148.75, $price);
    }

    public function test_get_latest_price_type_combined_prefers_stored(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();
        $account = AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user)->create(), 'config')->create();

        // Create stored price (newer)
        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-16',
            'price' => 151.00,
        ]);

        // Create transaction price (older)
        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account, 'account')->state(['price' => 148.75]), 'config')
            ->create([
                'date' => '2024-01-15',
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $price = $service->getLatestPrice($investment, 'combined');

        $this->assertEquals(151.00, $price);
    }

    public function test_get_latest_price_type_combined_falls_back_to_transaction(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();
        $account = AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user)->create(), 'config')->create();

        // Create stored price (older)
        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-14',
            'price' => 149.00,
        ]);

        // Create transaction price (newer)
        Transaction::factory()
            ->for($user)
            ->for(TransactionDetailInvestment::factory()->for($investment)->for($account, 'account')->state(['price' => 150.50]), 'config')
            ->create([
                'date' => '2024-01-16',
                'transaction_type' => 'buy',
                'schedule' => false,
            ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $price = $service->getLatestPrice($investment, 'combined');

        $this->assertEquals(150.50, $price);
    }

    public function test_get_latest_price_with_on_or_before_date(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();

        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-20',
            'price' => 155.00,
        ]);

        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-15',
            'price' => 150.25,
        ]);

        InvestmentPrice::factory()->for($investment)->create([
            'date' => '2024-01-10',
            'price' => 145.00,
        ]);

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $price = $service->getLatestPrice($investment, 'stored', Carbon::parse('2024-01-16'));

        $this->assertEquals(150.25, $price);
    }

    public function test_get_latest_price_returns_null_when_none_found(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->withUser($user)->create();

        $registry = new InvestmentPriceProviderRegistry();
        $service = $this->createService($registry);

        $price = $service->getLatestPrice($investment);

        $this->assertNull($price);
    }
}
