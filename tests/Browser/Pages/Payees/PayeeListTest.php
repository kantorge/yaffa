<?php

namespace Tests\Browser\Pages\Payees;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('extended')]
class PayeeListTest extends DuskTestCase
{
    public function test_user_can_add_a_new_payee_from_the_list_modal_and_filter_it_without_reloading(): void
    {
        /** @var User $user */
        $user = User::firstWhere('email', $this::USER_EMAIL);

        /** @var Category $category */
        $category = $user->categories()
            ->where('active', true)
            ->firstOrFail();

        $payeeName = 'Modal Payee ' . Str::uuid();

        $this->browse(function (Browser $browser) use ($user, $category, $payeeName) {
            $browser
                ->loginAs($user)
                ->visitRoute('account-entity.index', ['type' => 'payee'])
                ->waitFor('@table-payees')
                ->assertPresent('@table-payees');

            $browser->waitUsing(10, 100, fn () => $browser->script("return $.fn.DataTable.isDataTable('#table');")[0] === true);

            $initialTotalCount = $this->getTableRowCount($browser, '#table');

            $browser->type('@input-table-filter-search', 'zzzz-no-payee-match');

            $browser->waitUsing(10, 100, fn () => $this->getTableRowCount($browser, '#table') === 0);

            $browser
                ->click('@button-new-payee')
                ->waitFor('#newPayeeModal.show', 10)
                ->type('#newPayeeModal #newPayeeModal-name', $payeeName)
                ->select2ExactSearch('#newPayeeModal #newPayeeModal-category_id', $category->full_name, 10)
                ->click('#newPayeeModal button[type="submit"]')
                ->waitUntilMissing('#newPayeeModal.show', 10);

            $browser->waitUsing(10, 100, fn () => $this->getTableRowCount($browser, '#table') === $initialTotalCount + 1);

            $browser->waitUsing(
                10,
                100,
                fn () => $browser->script(
                    'return $("#table").DataTable().rows({ search: "applied" }).data().toArray().some(function (row) { return row.name === ' . json_encode($payeeName) . '; });'
                )[0] === true
            );

            $browser->assertInputValue('@input-table-filter-search', '');

            $browser->type('@input-table-filter-search', $payeeName);

            $browser->waitUsing(10, 100, fn () => $this->getTableRowCount($browser, '#table') === 1);

            $browser
                ->click('label[for=table_filter_active_yes]')
                ->click('label[for=table_filter_default_category_yes]');

            $browser->waitUsing(10, 100, fn () => $this->getTableRowCount($browser, '#table') === 1);

            $browser->assertScript(
                'return $("#table").DataTable().rows({ search: "applied" }).data().toArray().some(function (row) { return row.name === ' . json_encode($payeeName) . ' && row.has_default_category === true && row.active === true; });',
                true,
            );
        });
    }
}
