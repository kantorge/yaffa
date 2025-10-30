<?php

namespace Tests\Browser\Pages\CurrencyRates;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CurrencyRateManagementTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_view_currency_rates(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create currencies for the user
        $fromCurrency = Currency::factory()->for($user)->create(['iso_code' => 'EUR']);
        $toCurrency = Currency::factory()->for($user)->create(['iso_code' => 'USD', 'base' => true]);

        // Create some rates
        CurrencyRate::factory()->count(3)->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                ->assertSee('Overview')
                ->assertSee('Actions')
                ->assertSee('Filters')
                ->assertSee('Currency rate values');
        });
    }

    public function test_user_can_add_currency_rate(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $fromCurrency = Currency::factory()->for($user)->create(['iso_code' => 'GBP']);
        $toCurrency = Currency::factory()->for($user)->create(['iso_code' => 'USD', 'base' => true]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Click add new rate button
                ->click('button:contains("Add new rate")')
                ->whenAvailable('#currencyRateModal', function (Browser $modal) {
                    $modal
                        ->assertSee('Add Currency Rate')
                        ->type('#rateDate', '2024-01-15')
                        ->type('#rateValue', '1.2345')
                        ->click('button:contains("Add")');
                })
                // Wait for modal to close
                ->waitUntilMissing('#currencyRateModal.show')
                // Wait for success toast
                ->waitFor('.toast.bg-success', 5)
                ->assertSee('Currency rate added');

            // Verify the rate was added to the database
            $this->assertDatabaseHas('currency_rates', [
                'from_id' => $fromCurrency->id,
                'to_id' => $toCurrency->id,
                'date' => '2024-01-15',
            ]);
        });
    }

    public function test_user_can_edit_currency_rate(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $fromCurrency = Currency::factory()->for($user)->create(['iso_code' => 'CHF']);
        $toCurrency = Currency::factory()->for($user)->create(['iso_code' => 'USD', 'base' => true]);

        $rate = CurrencyRate::factory()->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
            'date' => '2024-01-10',
            'rate' => 1.1111,
        ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency, $rate) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Click edit button
                ->click('.edit-rate[data-id="' . $rate->id . '"]')
                ->whenAvailable('#currencyRateModal', function (Browser $modal) {
                    $modal
                        ->assertSee('Edit Currency Rate')
                        ->clear('#rateValue')
                        ->type('#rateValue', '1.2222')
                        ->click('button:contains("Update")');
                })
                // Wait for modal to close
                ->waitUntilMissing('#currencyRateModal.show')
                // Wait for success toast
                ->waitFor('.toast.bg-success', 5)
                ->assertSee('Currency rate updated');

            // Verify the rate was updated
            $this->assertDatabaseHas('currency_rates', [
                'id' => $rate->id,
                'rate' => 1.2222,
            ]);
        });
    }

    public function test_user_can_delete_currency_rate(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $fromCurrency = Currency::factory()->for($user)->create(['iso_code' => 'JPY']);
        $toCurrency = Currency::factory()->for($user)->create(['iso_code' => 'USD', 'base' => true]);

        $rate = CurrencyRate::factory()->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
            'date' => '2024-01-10',
            'rate' => 150.0,
        ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency, $rate) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Click delete button
                ->click('.delete-rate[data-id="' . $rate->id . '"]')
                // Wait for SweetAlert2 confirmation
                ->waitFor('.swal2-popup', 5)
                ->assertSee('Are you sure to want to delete this item?')
                // Confirm deletion
                ->click('.swal2-confirm')
                // Wait for success toast
                ->waitFor('.toast.bg-success', 5)
                ->assertSee('Currency rate deleted');

            // Verify the rate was deleted
            $this->assertDatabaseMissing('currency_rates', [
                'id' => $rate->id,
            ]);
        });
    }

    public function test_user_can_filter_rates_by_date_range(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $fromCurrency = Currency::factory()->for($user)->create(['iso_code' => 'CAD']);
        $toCurrency = Currency::factory()->for($user)->create(['iso_code' => 'USD', 'base' => true]);

        // Create rates with different dates
        CurrencyRate::factory()->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
            'date' => '2024-01-10',
            'rate' => 1.1,
        ]);

        CurrencyRate::factory()->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2,
        ]);

        CurrencyRate::factory()->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
            'date' => '2024-01-20',
            'rate' => 1.3,
        ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Select date range preset
                ->select('#dateRangePickerPresets', 'thisMonth')
                ->pause(1000) // Wait for filter to apply
                // Verify table shows filtered results
                ->assertPresent('#ratesTable');
        });
    }

    public function test_validation_prevents_duplicate_rates(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $fromCurrency = Currency::factory()->for($user)->create(['iso_code' => 'AUD']);
        $toCurrency = Currency::factory()->for($user)->create(['iso_code' => 'USD', 'base' => true]);

        // Create existing rate
        CurrencyRate::factory()->create([
            'from_id' => $fromCurrency->id,
            'to_id' => $toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 0.75,
        ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Try to add duplicate rate
                ->click('button:contains("Add new rate")')
                ->whenAvailable('#currencyRateModal', function (Browser $modal) {
                    $modal
                        ->type('#rateDate', '2024-01-15')
                        ->type('#rateValue', '0.76')
                        ->click('button:contains("Add")');
                })
                // Should show validation error
                ->waitFor('.invalid-feedback', 5)
                ->assertSee('has already been taken');
        });
    }
}
