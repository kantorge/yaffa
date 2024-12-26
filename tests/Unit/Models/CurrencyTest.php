<?php

namespace Tests\Unit\Models;

use App\Exceptions\CurrencyRateConversionException;
use App\Models\Currency;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kantorge\CurrencyExchangeRates\ApiClients\ExchangeRateApiClientInterface;
use Kantorge\CurrencyExchangeRates\Facades\CurrencyExchangeRates;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieve_supported_currency_rates_successfully()
    {
        // Create a user
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create([
            'base' => true
        ]);

        /** @var Currency $currency */
        $currency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['EUR'])
            ->create([
            'base' => false
        ]);

        $dateFrom = Carbon::parse('2023-01-01');

        $interface = \Mockery::mock(ExchangeRateApiClientInterface::class);
        CurrencyExchangeRates::shouldReceive('create')
            ->andReturn($interface);
        $interface->shouldReceive('isCurrencySupported')
            ->with($currency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('isCurrencySupported')
            ->with($baseCurrency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('getTimeSeries')
            ->andReturn([
                '2023-01-01' => [$baseCurrency->iso_code => 1.2]
            ]);

        $currency->retrieveCurrencyRateToBase($dateFrom);

        $this->assertDatabaseHas('currency_rates', [
            'from_id' => $currency->id,
            'to_id' => $baseCurrency->id,
            'date' => '2023-01-01',
            'rate' => 1.2
        ]);
    }

    public function test_throws_exception_when_currency_is_same_as_base()
    {
        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()->create([
            'base' => true
        ]);

        $this->expectException(CurrencyRateConversionException::class);
        $this->expectExceptionMessage('Currency is the same as the base currency');

        $baseCurrency->retrieveCurrencyRateToBase();
    }

    public function test_throws_exception_for_currency_not_supported_by_api()
    {
        $this->expectException(CurrencyRateConversionException::class);
        $this->expectExceptionMessage('One or more of the currencies are not supported by the API');

        // Create a user
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create([
                'base' => true
            ]);

        /** @var Currency $currency */
        $currency = Currency::factory()
            ->for($user)
            ->create([
                'base' => false,
                'iso_code' => 'XXX',
                'name' => 'Unknown currency'
            ]);

        $interface = \Mockery::mock(ExchangeRateApiClientInterface::class);
        CurrencyExchangeRates::shouldReceive('create')
            ->andReturn($interface);
        $interface->shouldReceive('isCurrencySupported')
            ->with($currency->iso_code)
            ->andReturn(false);

        $currency->retrieveCurrencyRateToBase();
    }

    public function test_throws_exception_when_no_data_returned_from_api()
    {
        $this->expectException(CurrencyRateConversionException::class);
        $this->expectExceptionMessage('No data returned from the API');

        // Create a user
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create([
                'base' => true
            ]);

        /** @var Currency $currency */
        $currency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['EUR'])
            ->create([
                'base' => false
            ]);

        $interface = \Mockery::mock(ExchangeRateApiClientInterface::class);
        CurrencyExchangeRates::shouldReceive('create')
            ->andReturn($interface);
        $interface->shouldReceive('isCurrencySupported')
            ->with($currency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('isCurrencySupported')
            ->with($baseCurrency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('getTimeSeries')
            ->andReturn([]);

        $currency->retrieveCurrencyRateToBase();
    }

    public function test_throws_exception_when_rate_is_out_of_range()
    {
        $this->expectException(CurrencyRateConversionException::class);
        $this->expectExceptionMessage('Currency rate is out of the valid range');

        // Create a user
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create([
                'base' => true
            ]);

        /** @var Currency $currency */
        $currency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['EUR'])
            ->create([
                'base' => false
            ]);

        $dateFrom = Carbon::parse('2023-01-01');

        $interface = \Mockery::mock(ExchangeRateApiClientInterface::class);
        CurrencyExchangeRates::shouldReceive('create')
            ->andReturn($interface);
        $interface->shouldReceive('isCurrencySupported')
            ->with($currency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('isCurrencySupported')
            ->with($baseCurrency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('getTimeSeries')
            ->andReturn([
                '2023-01-01' => [$baseCurrency->iso_code => 10000000000.0]
            ]);

        $currency->retrieveCurrencyRateToBase($dateFrom);
    }

    public function test_throws_exception_when_rate_is_negative()
    {
        $this->expectException(CurrencyRateConversionException::class);
        $this->expectExceptionMessage('Currency rate is out of the valid range');

        // Create a user
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create([
                'base' => true
            ]);

        /** @var Currency $currency */
        $currency = Currency::factory()
            ->for($user)
            ->fromIsoCodes(['EUR'])
            ->create([
                'base' => false
            ]);

        $dateFrom = Carbon::parse('2023-01-01');

        $interface = \Mockery::mock(ExchangeRateApiClientInterface::class);
        CurrencyExchangeRates::shouldReceive('create')
            ->andReturn($interface);
        $interface->shouldReceive('isCurrencySupported')
            ->with($currency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('isCurrencySupported')
            ->with($baseCurrency->iso_code)
            ->andReturn(true);
        $interface->shouldReceive('getTimeSeries')
            ->andReturn([
                '2023-01-01' => [$baseCurrency->iso_code => -1.0]
            ]);

        $currency->retrieveCurrencyRateToBase($dateFrom);
    }
}
