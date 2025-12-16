<?php

namespace Tests\Browser\Pages\CurrencyRates;

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
        // Get the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // The user is expected to have EUR and USD currencies
        $fromCurrency = $user->currencies->firstWhere('iso_code', 'EUR');
        $toCurrency = $user->currencies->firstWhere('iso_code', 'USD');

        // Create some rates
        CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->count(3)
            ->create();

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // The main test user has English as preferred language by default
                ->assertSee('Overview')
                ->assertSee('Actions')
                ->assertSee('Date')
                ->assertSee('Currency rate values');
        });
    }

    public function test_user_can_add_currency_rate(): void
    {
        // Get the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // The user is expected to have EUR and USD currencies
        $fromCurrency = $user->currencies->firstWhere('iso_code', 'EUR');
        $toCurrency = $user->currencies->firstWhere('iso_code', 'USD');

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Click add new rate button. TODO: add a more robust selector
                ->click('button > span.fa.fa-fw.fa-plus')
                ->whenAvailable('#currencyRateModal.show', function (Browser $modal) {
                    $modal
                        ->assertSee('Add Currency Rate')
                        // This is expexted to be a date input in US format (mm/dd/yyyy)
                        ->type('#rateDate', '01/15/2024')
                        ->type('#rateValue', '1.2345')
                        ->click('button.btn-primary');
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
        // Get the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // The user is expected to have EUR and USD currencies
        $fromCurrency = $user->currencies->firstWhere('iso_code', 'EUR');
        $toCurrency = $user->currencies->firstWhere('iso_code', 'USD');

        $rate = CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->create([
                'date' => '2024-02-01',
                'rate' => 1.1111,
            ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency, $rate) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Click edit button
                ->click('.edit-rate[data-id="' . $rate->id . '"]')
                ->whenAvailable('#currencyRateModal.show', function (Browser $modal) {
                    $modal
                        ->assertSee('Edit Currency Rate')
                        ->clear('#rateValue')
                        ->type('#rateValue', '1.2222')
                        ->click('button.btn-primary');
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
        // Get the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // The user is expected to have EUR and USD currencies
        $fromCurrency = $user->currencies->firstWhere('iso_code', 'EUR');
        $toCurrency = $user->currencies->firstWhere('iso_code', 'USD');

        $rate = CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->create([
                'date' => '2024-02-02',
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
        // Get the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // The user is expected to have EUR and USD currencies
        $fromCurrency = $user->currencies->firstWhere('iso_code', 'EUR');
        $toCurrency = $user->currencies->firstWhere('iso_code', 'USD');

        // Create rates with different dates
        CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->create([
                'date' => '2024-02-10',
                'rate' => 1.1,
            ]);

        CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->create([
                'date' => '2024-02-15',
                'rate' => 1.2,
            ]);

        CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->create([
                'date' => '2024-02-20',
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
        // Get the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // The user is expected to have EUR and USD currencies
        $fromCurrency = $user->currencies->firstWhere('iso_code', 'EUR');
        $toCurrency = $user->currencies->firstWhere('iso_code', 'USD');

        // Create existing rate
        CurrencyRate::factory()
            ->betweenCurrencies($fromCurrency, $toCurrency)
            ->create([
                'date' => '2024-03-15',
                'rate' => 0.75,
            ]);

        $this->browse(function (Browser $browser) use ($user, $fromCurrency, $toCurrency) {
            $browser
                ->loginAs($user)
                ->visitRoute('currency-rate.index', ['from' => $fromCurrency, 'to' => $toCurrency])
                ->waitFor('#currencyRateApp')
                // Try to add duplicate rate
                ->click('button > span.fa.fa-fw.fa-plus')
                ->whenAvailable('#currencyRateModal.show', function (Browser $modal) {
                    $modal
                        // This is expexted to be a date input in US format (mm/dd/yyyy)
                        ->type('#rateDate', '03/15/2024')
                        ->type('#rateValue', '0.76')
                        ->click('button.btn-primary');
                })
                // Should show validation error
                ->waitFor('.invalid-feedback', 5)
                ->assertSee('has already been taken');
        });
    }
}
