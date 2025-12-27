<?php

namespace Tests\Browser\Pages\Reports\FindTransactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FindTransactionsFilterBehaviorTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }

        $this->user = User::firstWhere('email', $this::USER_EMAIL);
    }
    public function test_date_selector_defaults_are_loaded_from_the_url(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['date_from' => '2022-01-01', 'date_to' => '2022-01-31'])
                ->waitFor('@dateRangePicker')
                ->assertInputValue('#date_from', '2022-01-01')
                ->assertInputValue('#date_to', '2022-01-31');

            $browser->visitRoute('reports.transactions', [])
                ->waitFor('@dateRangePicker')
                ->assertInputValue('#date_from', '')
                ->assertInputValue('#date_to', '');
        });
    }

    public function test_date_selector_preset_selections_are_respected(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions')
                ->waitFor('@dateRangePicker')
                // Select option with value "thisMonth"
                ->select('#dateRangePickerPresets', 'thisMonth')
                ->assertInputValue('#date_from', date('Y-m-01'))
                ->assertInputValue('#date_to', date('Y-m-t'))
                // Check the parameters in the URL
                ->assertQueryStringHas('date_from', date('Y-m-01'))
                ->assertQueryStringHas('date_to', date('Y-m-t'))
                // Remove the selection with the option "placeholder"
                ->select('#dateRangePickerPresets', 'placeholder')
                ->assertInputValue('#date_from', '')
                ->assertInputValue('#date_to', '')
                // Check the parameters in the URL
                ->assertQueryStringMissing('date_from')
                ->assertQueryStringMissing('date_to');
        });
    }

    public function test_date_selector_clear_button_behavior(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions')
                ->waitFor('@dateRangePicker')
                // Set the date range using the presets
                ->select('#dateRangePickerPresets', 'thisMonth')
                ->assertInputValue('#date_from', date('Y-m-01'))
                ->assertInputValue('#date_to', date('Y-m-t'))
                // Clear the date range
                ->click('#clearDateSelection')
                ->assertInputValue('#date_from', '')
                ->assertInputValue('#date_to', '')
                ->assertSelected('#dateRangePickerPresets', 'placeholder');
        });
    }

    public function test_tag_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two tags
        $tag1 = $this->user->tags->first();
        $tag2 = $this->user->tags->skip(1)->first();

        $this->browse(function (Browser $browser) {
            // Test with no tags
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitFor('#findTransactionsActionsCard', 10)
                ->scrollIntoView('#findTransactionsSelectCard-tag')
                //->waitUntilVue('presetsReady.tag', true, '@component-find-transactions', 10)
                ->assertVue('selectedTags', [], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($tag1) {
            // Test with one tag
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['tags' => [$tag1->id]])
                ->waitFor('#findTransactionContainer', 10)
                ->waitUntilVue('presetsReady.tag', true, '@component-find-transactions')
                ->assertVue('selectedTags', [$tag1->id], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($tag1, $tag2) {
            // Test with two tags
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['tags' => [$tag1->id, $tag2->id]])
                ->waitUntilVue('presetsReady.tag', true, '@component-find-transactions')
                ->assertVue('selectedTags', [$tag1->id, $tag2->id], '@component-find-transactions');
        });
    }

    public function test_category_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two categories
        $category1 = $this->user->categories->whereNotNull('parent_id')->first();
        $category2 = $this->user->categories->whereNotNull('parent_id')->skip(1)->first();

        $this->browse(function (Browser $browser) {
            // Test with no categories
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitUntilVue('presetsReady.category', true, '@component-find-transactions')
                ->assertVue('selectedCategories', [], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($category1) {
            // Test with one category
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['categories' => [$category1->id]])
                ->waitUntilVue('presetsReady.category', true, '@component-find-transactions')
                ->assertVue('selectedCategories', [$category1->id], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($category1, $category2) {
            // Test with two categories
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['categories' => [$category1->id, $category2->id]])
                ->waitUntilVue('presetsReady.category', true, '@component-find-transactions')
                ->assertVue('selectedCategories', [$category1->id, $category2->id], '@component-find-transactions');
        });
    }

    public function test_account_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two accounts
        $account1 = $this->user->accounts->first();
        $account2 = $this->user->accounts->skip(1)->first();

        $this->browse(function (Browser $browser) {
            // Test with no accounts
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitUntilVue('presetsReady.account', true, '@component-find-transactions')
                ->assertVue('selectedAccounts', [], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($account1) {
            // Test with one account
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['accounts' => [$account1->id]])
                ->waitUntilVue('presetsReady.account', true, '@component-find-transactions')
                ->assertVue('selectedAccounts', [$account1->id], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($account1, $account2) {
            // Test with two accounts
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['accounts' => [$account1->id, $account2->id]])
                ->waitUntilVue('presetsReady.account', true, '@component-find-transactions')
                ->assertVue('selectedAccounts', [$account1->id, $account2->id], '@component-find-transactions');
        });
    }

    public function test_payee_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two payees
        $payee1 = $this->user->payees->first();
        $payee2 = $this->user->payees->skip(1)->first();

        $this->browse(function (Browser $browser) {
            // Test with no payees
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitUntilVue('presetsReady.payee', true, '@component-find-transactions')
                ->assertVue('selectedPayees', [], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($payee1) {
            // Test with one payee
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['payees' => [$payee1->id]])
                ->waitUntilVue('presetsReady.payee', true, '@component-find-transactions')
                ->assertVue('selectedPayees', [$payee1->id], '@component-find-transactions');
        });

        $this->browse(function (Browser $browser) use ($payee1, $payee2) {
            // Test with two payees
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['payees' => [$payee1->id, $payee2->id]])
                ->waitUntilVue('presetsReady.payee', true, '@component-find-transactions')
                ->assertVue('selectedPayees', [$payee1->id, $payee2->id], '@component-find-transactions');
        });
    }
}
