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
                // Wait for Select2 to be initialized
                ->waitFor('#select_tag.select2-hidden-accessible', 10)
                // Verify Select2 element has no values selected
                ->assertScript("return $('#select_tag').select2('data').length === 0", true);
        });

        $this->browse(function (Browser $browser) use ($tag1) {
            // Test with one tag
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['tags' => [$tag1->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_tag.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser, $tag1) {
                    return $browser->script("return $('#select_tag').select2('val').length")[0] === 1;
                })
                // Verify Select2 has the correct tag selected by checking selected values
                ->assertScript("return $('#select_tag').select2('val').includes('" . $tag1->id . "')", true)
                ->assertScript("return $('#select_tag').select2('val').length === 1", true);
        });

        $this->browse(function (Browser $browser) use ($tag1, $tag2) {
            // Test with two tags
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['tags' => [$tag1->id, $tag2->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_tag.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_tag').select2('val').length")[0] === 2;
                })
                // Verify Select2 has both tags selected
                ->assertScript("return $('#select_tag').select2('val').includes('" . $tag1->id . "')", true)
                ->assertScript("return $('#select_tag').select2('val').includes('" . $tag2->id . "')", true)
                ->assertScript("return $('#select_tag').select2('val').length === 2", true);
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
                // Wait for Select2 to be initialized
                ->waitFor('#select_category.select2-hidden-accessible', 10)
                // Verify Select2 element has no values selected
                ->assertScript("return $('#select_category').select2('data').length === 0", true);
        });

        $this->browse(function (Browser $browser) use ($category1) {
            // Test with one category
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['categories' => [$category1->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_category.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_category').select2('val').length")[0] === 1;
                })
                // Verify Select2 has the correct category selected
                ->assertScript("return $('#select_category').select2('val').includes('" . $category1->id . "')", true)
                ->assertScript("return $('#select_category').select2('val').length === 1", true);
        });

        $this->browse(function (Browser $browser) use ($category1, $category2) {
            // Test with two categories
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['categories' => [$category1->id, $category2->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_category.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_category').select2('val').length")[0] === 2;
                })
                // Verify Select2 has both categories selected
                ->assertScript("return $('#select_category').select2('val').includes('" . $category1->id . "')", true)
                ->assertScript("return $('#select_category').select2('val').includes('" . $category2->id . "')", true)
                ->assertScript("return $('#select_category').select2('val').length === 2", true);
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
                // Wait for Select2 to be initialized
                ->waitFor('#select_account.select2-hidden-accessible', 10)
                // Verify Select2 element has no values selected
                ->assertScript("return $('#select_account').select2('data').length === 0", true);
        });

        $this->browse(function (Browser $browser) use ($account1) {
            // Test with one account
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['accounts' => [$account1->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_account.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_account').select2('val').length")[0] === 1;
                })
                // Verify Select2 has the correct account selected
                ->assertScript("return $('#select_account').select2('val').includes('" . $account1->id . "')", true)
                ->assertScript("return $('#select_account').select2('val').length === 1", true);
        });

        $this->browse(function (Browser $browser) use ($account1, $account2) {
            // Test with two accounts
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['accounts' => [$account1->id, $account2->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_account.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_account').select2('val').length")[0] === 2;
                })
                // Verify Select2 has both accounts selected
                ->assertScript("return $('#select_account').select2('val').includes('" . $account1->id . "')", true)
                ->assertScript("return $('#select_account').select2('val').includes('" . $account2->id . "')", true)
                ->assertScript("return $('#select_account').select2('val').length === 2", true);
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
                // Wait for Select2 to be initialized
                ->waitFor('#select_payee.select2-hidden-accessible', 10)
                // Verify Select2 element has no values selected
                ->assertScript("return $('#select_payee').select2('data').length === 0", true);
        });

        $this->browse(function (Browser $browser) use ($payee1) {
            // Test with one payee
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['payees' => [$payee1->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_payee.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_payee').select2('val').length")[0] === 1;
                })
                // Verify Select2 has the correct payee selected
                ->assertScript("return $('#select_payee').select2('val').includes('" . $payee1->id . "')", true)
                ->assertScript("return $('#select_payee').select2('val').length === 1", true);
        });

        $this->browse(function (Browser $browser) use ($payee1, $payee2) {
            // Test with two payees
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['payees' => [$payee1->id, $payee2->id]])
                // Wait for Select2 to be initialized
                ->waitFor('#select_payee.select2-hidden-accessible', 10)
                // Wait for preset values to be loaded via AJAX
                ->waitUsing(10, 100, function () use ($browser) {
                    return $browser->script("return $('#select_payee').select2('val').length")[0] === 2;
                })
                // Verify Select2 has both payees selected
                ->assertScript("return $('#select_payee').select2('val').includes('" . $payee1->id . "')", true)
                ->assertScript("return $('#select_payee').select2('val').includes('" . $payee2->id . "')", true)
                ->assertScript("return $('#select_payee').select2('val').length === 2", true);
        });
    }
}
