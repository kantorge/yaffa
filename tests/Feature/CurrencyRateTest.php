<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Carbon\Carbon;
use Kantorge\CurrencyExchangeRates\Facades\CurrencyExchangeRates;
use Tests\TestCase;

class CurrencyRateTest extends TestCase
{
    private function createUniqeCurrencyForUser(User $user): Currency
    {
        // Get the currencies supported by the mock provider
        $currencyApi = CurrencyExchangeRates::create();
        $currencies = $currencyApi->getSupportedCurrencies();

        $loopCount = 0;
        $loopLimit = 10000;
        do {
            /** @var Currency $currency */
            $currency = Currency::factory()
                ->for($user)
                ->fromIsoCodes($currencies)
                ->make();

            if ($loopCount++ > $loopLimit) {
                $this->fail("Loop limit of {$loopLimit} reached while trying to create unique currency");
            }
        } while (
            Currency::where('user_id', $user->id)
                ->where('iso_code', $currency->iso_code)
                ->count() > 0
        );
        $currency->save();

        return $currency;
    }

    public function test_guest_cannot_access_resource(): void
    {
        // For this test, set the data provider of the currency exchange rate API to mock
        config(['currency-exchange-rates.default_provider' => 'mock']);

        // Create a test user and two currencies for that user
        /** @var User $user */
        $user = User::factory()->create();

        // Create a base currency for the user
        $baseCurrency = $this->createUniqeCurrencyForUser($user);
        $baseCurrency->base = true;
        $baseCurrency->save();

        $otherCurrency = $this->createUniqeCurrencyForUser($user);

        // Add one currency rate record
        $rate = CurrencyRate::create([
            'from_id' => $otherCurrency->id,
            'to_id' => $baseCurrency->id,
            'rate' => 1,
            'date' => now()
        ]);

        // Acting as a guest, try to access various routes
        $this->get(route("currency-rate.index", [
            'from' => $otherCurrency,
            'to' => $baseCurrency
        ]))
            ->assertRedirect(route('login'));

        $this->delete(route("currency-rate.destroy", $rate))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_access_their_own_resources(): void
    {
        // For this test, set the data provider of the currency exchange rate API to mock
        config(['currency-exchange-rates.default_provider' => 'mock']);

        // Create a test user and two currencies for that user
        /** @var User $user */
        $user = User::factory()->create();

        // Create a base currency for the user
        $baseCurrency = $this->createUniqeCurrencyForUser($user);
        $baseCurrency->base = true;
        $baseCurrency->save();

        $otherCurrency = $this->createUniqeCurrencyForUser($user);

        // Add one currency rate record
        CurrencyRate::create([
            'from_id' => $otherCurrency->id,
            'to_id' => $baseCurrency->id,
            'rate' => 1,
            'date' => Carbon::yesterday()
        ]);

        // Acting as the user, try to access various routes
        $this->actingAs($user)
            ->get(route("currency-rate.index", [
                'from' => $otherCurrency,
                'to' => $baseCurrency
            ]))
            ->assertStatus(200)
            ->assertViewIs("currency-rate.index");
    }
}
