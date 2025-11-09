<?php

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyRateService $service;
    private User $user;
    private Currency $fromCurrency;
    private Currency $toCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CurrencyRateService();
        $this->user = User::factory()->create();
        
        // Create unique currencies for this test using randomized 3-letter codes
        // Generate a random suffix to ensure uniqueness within the 3-letter limit
        $codes = ['EUR', 'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN'];
        shuffle($codes);
        
        $this->fromCurrency = Currency::factory()->for($this->user)->create([
            'iso_code' => $codes[0],
            'name' => 'Test Currency From',
        ]);
        $this->toCurrency = Currency::factory()->for($this->user)->create([
            'iso_code' => $codes[1],
            'name' => 'Test Currency To',
            'base' => true,
        ]);
    }

    public function test_can_create_rate(): void
    {
        $data = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ];

        $rate = $this->service->createRate($data);

        $this->assertInstanceOf(CurrencyRate::class, $rate);
        $this->assertEquals($data['from_id'], $rate->from_id);
        $this->assertEquals($data['to_id'], $rate->to_id);
        $this->assertEquals($data['date'], $rate->date->format('Y-m-d'));
        $this->assertEquals($data['rate'], $rate->rate);
        $this->assertDatabaseHas('currency_rates', [
            'from_id' => $data['from_id'],
            'to_id' => $data['to_id'],
            'date' => $data['date'],
        ]);
    }

    public function test_can_update_rate(): void
    {
        $rate = CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ]);

        $updateData = [
            'date' => '2024-01-16',
            'rate' => 1.3456,
        ];

        $updatedRate = $this->service->updateRate($rate, $updateData);

        $this->assertEquals($updateData['date'], $updatedRate->date->format('Y-m-d'));
        $this->assertEquals($updateData['rate'], $updatedRate->rate);
        $this->assertDatabaseHas('currency_rates', [
            'id' => $rate->id,
            'date' => $updateData['date'],
            'rate' => $updateData['rate'],
        ]);
    }

    public function test_can_delete_rate(): void
    {
        $rate = CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ]);

        $result = $this->service->deleteRate($rate);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('currency_rates', [
            'id' => $rate->id,
        ]);
    }

    public function test_can_get_all_rates(): void
    {
        CurrencyRate::factory()->count(3)->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
        ]);

        // Create rates for a different currency pair
        $otherCurrency = Currency::factory()->for($this->user)->create(['iso_code' => 'GBP']);
        CurrencyRate::factory()->count(2)->create([
            'from_id' => $otherCurrency->id,
            'to_id' => $this->toCurrency->id,
        ]);

        $rates = $this->service->getAllRates($this->fromCurrency->id, $this->toCurrency->id);

        $this->assertCount(3, $rates);
        $rates->each(function ($rate) {
            $this->assertEquals($this->fromCurrency->id, $rate->from_id);
            $this->assertEquals($this->toCurrency->id, $rate->to_id);
        });
    }

    public function test_can_get_rates_by_date_range(): void
    {
        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-10',
            'rate' => 1.1,
        ]);

        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2,
        ]);

        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-20',
            'rate' => 1.3,
        ]);

        // Test with both date_from and date_to
        $rates = $this->service->getRatesByDateRange(
            $this->fromCurrency->id,
            $this->toCurrency->id,
            '2024-01-12',
            '2024-01-18'
        );

        $this->assertCount(1, $rates);
        $this->assertEquals('2024-01-15', $rates->first()->date->format('Y-m-d'));
    }

    public function test_can_get_rates_with_only_date_from(): void
    {
        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-10',
            'rate' => 1.1,
        ]);

        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2,
        ]);

        $rates = $this->service->getRatesByDateRange(
            $this->fromCurrency->id,
            $this->toCurrency->id,
            '2024-01-12',
            null
        );

        $this->assertCount(1, $rates);
        $this->assertEquals('2024-01-15', $rates->first()->date->format('Y-m-d'));
    }

    public function test_can_get_rates_with_only_date_to(): void
    {
        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-10',
            'rate' => 1.1,
        ]);

        CurrencyRate::factory()->create([
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2,
        ]);

        $rates = $this->service->getRatesByDateRange(
            $this->fromCurrency->id,
            $this->toCurrency->id,
            null,
            '2024-01-12'
        );

        $this->assertCount(1, $rates);
        $this->assertEquals('2024-01-10', $rates->first()->date->format('Y-m-d'));
    }
}
