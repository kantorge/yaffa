<?php

namespace Tests\Browser\Pages\Reports\FindTransactions;

use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('critical')]
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
                ->waitFor('#select_tag.select2-hidden-accessible', 10);

            $this->assertSelect2HasNoSelection($browser, '#select_tag');
        });

        $this->browse(function (Browser $browser) use ($tag1) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['tags' => [$tag1->id]])
                ->waitFor('#select_tag.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_tag', 1);
            $this->assertSelect2Values($browser, '#select_tag', [$tag1->id]);
        });

        $this->browse(function (Browser $browser) use ($tag1, $tag2) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['tags' => [$tag1->id, $tag2->id]])
                ->waitFor('#select_tag.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_tag', 2);
            $this->assertSelect2Values($browser, '#select_tag', [$tag1->id, $tag2->id]);
        });
    }

    public function test_category_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two categories
        $category1 = $this->user->categories->whereNotNull('parent_id')->first();
        $category2 = $this->user->categories->whereNotNull('parent_id')->skip(1)->first();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitFor('#select_category.select2-hidden-accessible', 10);

            $this->assertSelect2HasNoSelection($browser, '#select_category');
        });

        $this->browse(function (Browser $browser) use ($category1) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['categories' => [$category1->id]])
                ->waitFor('#select_category.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_category', 1);
            $this->assertSelect2Values($browser, '#select_category', [$category1->id]);
        });

        $this->browse(function (Browser $browser) use ($category1, $category2) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['categories' => [$category1->id, $category2->id]])
                ->waitFor('#select_category.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_category', 2);
            $this->assertSelect2Values($browser, '#select_category', [$category1->id, $category2->id]);
        });
    }

    public function test_account_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two accounts
        $account1 = $this->user->accounts->first();
        $account2 = $this->user->accounts->skip(1)->first();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitFor('#select_account.select2-hidden-accessible', 10);

            $this->assertSelect2HasNoSelection($browser, '#select_account');
        });

        $this->browse(function (Browser $browser) use ($account1) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['accounts' => [$account1->id]])
                ->waitFor('#select_account.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_account', 1);
            $this->assertSelect2Values($browser, '#select_account', [$account1->id]);
        });

        $this->browse(function (Browser $browser) use ($account1, $account2) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['accounts' => [$account1->id, $account2->id]])
                ->waitFor('#select_account.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_account', 2);
            $this->assertSelect2Values($browser, '#select_account', [$account1->id, $account2->id]);
        });
    }

    public function test_payee_selector_defaults_are_loaded_from_the_url(): void
    {
        // The default user is assumed to have at least two payees
        $payee1 = $this->user->payees->first();
        $payee2 = $this->user->payees->skip(1)->first();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [])
                ->waitFor('#select_payee.select2-hidden-accessible', 10);

            $this->assertSelect2HasNoSelection($browser, '#select_payee');
        });

        $this->browse(function (Browser $browser) use ($payee1) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['payees' => [$payee1->id]])
                ->waitFor('#select_payee.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_payee', 1);
            $this->assertSelect2Values($browser, '#select_payee', [$payee1->id]);
        });

        $this->browse(function (Browser $browser) use ($payee1, $payee2) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['payees' => [$payee1->id, $payee2->id]])
                ->waitFor('#select_payee.select2-hidden-accessible', 10);

            $this->waitForSelect2ValueCount($browser, '#select_payee', 2);
            $this->assertSelect2Values($browser, '#select_payee', [$payee1->id, $payee2->id]);
        });
    }

    public function test_transaction_delete_requires_confirmation_and_stays_deleted_across_tab_switches(): void
    {
        $transactionDate = now()->format('Y-m-d');

        $transaction = Transaction::factory()
            ->for($this->user)
            ->deposit($this->user)
            ->create([
                'date' => $transactionDate,
            ]);

        $this->browse(function (Browser $browser) use ($transaction, $transactionDate) {
            $deleteSelector = '#tab-transaction-list table button[data-delete][data-id="' . $transaction->id . '"]';

            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', [
                    'date_from' => $transactionDate,
                    'date_to' => $transactionDate,
                ])
                ->waitFor('#nav-transaction-list', 10)
                ->click('#nav-transaction-list')
                ->waitFor('#tab-transaction-list table', 10)
                ->waitFor($deleteSelector, 30);

            $initialCount = $this->getTableRowCount($browser, '#tab-transaction-list table');
            $this->assertGreaterThan(0, $initialCount);

            $browser->click($deleteSelector)
                ->waitFor('.swal2-container', 10)
                ->click('.swal2-cancel')
                ->waitUntilMissing('.swal2-container', 10)
                ->pause(250);

            $countAfterCancel = $this->getTableRowCount($browser, '#tab-transaction-list table');
            $this->assertSame($initialCount, $countAfterCancel);

            $browser->click($deleteSelector)
                ->waitFor('.swal2-container', 10)
                ->click('.swal2-confirm')
                ->waitUntilMissing($deleteSelector, 10)
                ->waitUsing(
                    10,
                    100,
                    fn () => $this->getTableRowCount($browser, '#tab-transaction-list table') === ($initialCount - 1)
                )
                ->click('#nav-summary')
                ->waitFor('#tab-summary', 10)
                ->click('#nav-transaction-list')
                ->waitFor('#tab-transaction-list table', 10)
                ->waitUntilMissing($deleteSelector, 10);

            $countAfterTabSwitch = $this->getTableRowCount($browser, '#tab-transaction-list table');
            $this->assertSame($initialCount - 1, $countAfterTabSwitch);
        });
    }
}
