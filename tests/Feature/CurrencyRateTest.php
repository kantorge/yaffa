<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Tests\TestCase;

class CurrencyRateTest extends TestCase
{
    /** @test */
    public function guest_cannot_access_resource()
    {
        // Create a test user and two currencies for that user
        /** @var User $user */
        $user = User::factory()->create();

        // Create a base currency for the user
        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()->for($user)->create(['base' => true]);

        // Create a non-base currency for the user,
        // while ensuring that the base currency is not the same as the non-base currency
        do {
            /** @var Currency $otherCurrency */
            $otherCurrency = Currency::factory()->for($user)->make();
        } while (
            Currency::where('user_id', $user->id)
                ->where('iso_code', $otherCurrency->iso_code)
                ->count()
        );
        $otherCurrency->save();

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

        $this->get(route("currency-rate.retreiveRate", [
            'currency' => $otherCurrency,
        ]))
            ->assertRedirect(route('login'));

        $this->get(route("currency-rate.retreiveMissing", [
            'currency' => $otherCurrency,
        ]))
            ->assertRedirect(route('login'));

        $this->delete(route("currency-rate.destroy", $rate))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function user_can_access_their_own_resources()
    {
        // Create a test user and two currencies for that user
        /** @var User $user */
        $user = User::factory()->create();

        // Create a base currency for the user
        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()->for($user)->create(['base' => true]);

        // Create a non-base currency for the user,
        // while ensuring that the base currency is not the same as the non-base currency
        do {
            /** @var Currency $otherCurrency */
            $otherCurrency = Currency::factory()->for($user)->make();
        } while (
            Currency::where('user_id', $user->id)
                ->where('iso_code', $otherCurrency->iso_code)
                ->count()
        );
        $otherCurrency->save();

        // Add one currency rate record
        $rate = CurrencyRate::create([
            'from_id' => $otherCurrency->id,
            'to_id' => $baseCurrency->id,
            'rate' => 1,
            'date' => now()
        ]);

        // Acting as the user, try to access various routes
        $this->actingAs($user)
            ->get(route("currency-rate.index", [
                'from' => $otherCurrency,
                'to' => $baseCurrency
            ]))
            ->assertStatus(200)
            ->assertViewIs("currency-rate.index");
        /*
        $this->actingAs($user)
            ->from(route("currency-rate.index", [
                'from' => $otherCurrency,
                'to' => $baseCurrency
            ]))
            ->get(route("currency-rate.retreiveRate", [
                'currency' => $otherCurrency,
            ]))
            ->assertStatus(200)
            ->assertViewIs("currency-rate.index");

        $this->actingAs($user)
            ->from(route("currency-rate.index", [
                'from' => $otherCurrency,
                'to' => $baseCurrency
            ]))
            ->get(route("currency-rate.retreiveMissing", [
                'currency' => $otherCurrency,
            ]))
            ->assertStatus(200)
            ->assertViewIs("currency-rate.index");
        */
    }
}
