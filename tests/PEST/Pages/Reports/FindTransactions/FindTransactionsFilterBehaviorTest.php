<?php

use App\Models\Transaction;
use App\Models\User;

/*
 * Converted from the Dusk test of the same name (tests/Browser/Pages/Reports/FindTransactions), one it() per method.
 */

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

const TRANSACTION_LIST_ROWS = "$('#tab-transaction-list table').DataTable().rows({ search: 'applied' }).count()";

it('loads the date selector defaults from the URL', function () {
    $page = visit(route('reports.transactions', ['date_from' => '2022-01-01', 'date_to' => '2022-01-31']));
    $this->waitUntil($page, "document.querySelector('#dateRangeFilter_from')");
    $page->assertValue('#dateRangeFilter_from', '2022-01-01')
        ->assertValue('#dateRangeFilter_to', '2022-01-31');

    $page = visit(route('reports.transactions'));
    $this->waitUntil($page, "document.querySelector('#dateRangeFilter_from')");
    $page->assertValue('#dateRangeFilter_from', '')
        ->assertValue('#dateRangeFilter_to', '');
})->group('critical');

it('respects the date selector preset selections', function () {
    $page = visit(route('reports.transactions'));
    $this->waitUntil($page, "document.querySelector('#dateRangeFilter_from')");

    $page->select('#dateRangeFilterPresets', 'thisMonth')
        ->assertValue('#dateRangeFilter_from', date('Y-m-01'))
        ->assertValue('#dateRangeFilter_to', date('Y-m-t'))
        // The preset key appears in the URL; the resolved dates don't
        ->assertQueryStringHas('date_preset', 'thisMonth')
        ->assertQueryStringMissing('date_from')
        ->assertQueryStringMissing('date_to');

    // Remove the selection with the "Select preset" option (value "none")
    $page->select('#dateRangeFilterPresets', 'none')
        ->assertValue('#dateRangeFilter_from', '')
        ->assertValue('#dateRangeFilter_to', '')
        ->assertQueryStringMissing('date_preset')
        ->assertQueryStringMissing('date_from')
        ->assertQueryStringMissing('date_to');
})->group('critical');

it('clears the date selector with the clear button', function () {
    $page = visit(route('reports.transactions'));
    $this->waitUntil($page, "document.querySelector('#dateRangeFilter_from')");

    $page->select('#dateRangeFilterPresets', 'thisMonth')
        ->assertValue('#dateRangeFilter_from', date('Y-m-01'))
        ->assertValue('#dateRangeFilter_to', date('Y-m-t'))
        ->click('#dateRangeFilterClear')
        ->assertValue('#dateRangeFilter_from', '')
        ->assertValue('#dateRangeFilter_to', '')
        ->assertSelected('#dateRangeFilterPresets', 'none');
})->group('critical');

/*
 * The four select cards share one shape: no URL param -> no selection; one or two IDs -> exactly those preset.
 * One it() per Dusk method, via a dataset keyed by the Dusk method's subject.
 */
it('loads the select card defaults from the URL', function (string $selectId, string $param, Closure $items) {
    // The demo user is assumed to have at least two of each
    [$first, $second] = $items($this->user);

    $page = visit(route('reports.transactions'));
    $this->assertTomSelectValues($page, $selectId, []);

    $page = visit(route('reports.transactions', [$param => [$first->id]]));
    $this->assertTomSelectValues($page, $selectId, [$first->id]);

    $page = visit(route('reports.transactions', [$param => [$first->id, $second->id]]));
    $this->assertTomSelectValues($page, $selectId, [$first->id, $second->id]);
})->with([
    'tag selector' => ['select_tag', 'tags', fn (User $user) => $user->tags()->take(2)->get()],
    'category selector' => ['select_category', 'categories', fn (User $user) => $user->categories()->whereNotNull('parent_id')->take(2)->get()],
    'account selector' => ['select_account', 'accounts', fn (User $user) => $user->accounts()->take(2)->get()],
    'payee selector' => ['select_payee', 'payees', fn (User $user) => $user->payees()->take(2)->get()],
])->group('critical');

it('requires confirmation to delete a transaction, which stays deleted across tab switches', function () {
    $transactionDate = now()->format('Y-m-d');
    $transaction = Transaction::factory()
        ->for($this->user)
        ->deposit($this->user)
        ->create(['date' => $transactionDate]);
    $deleteSelector = "#tab-transaction-list table button[data-delete][data-id=\"{$transaction->id}\"]";

    $page = visit(route('reports.transactions', ['date_from' => $transactionDate, 'date_to' => $transactionDate]));
    $this->waitUntil($page, "document.querySelector('#nav-transaction-list')");
    $page->click('#nav-transaction-list');
    $this->waitUntil($page, "document.querySelector('{$deleteSelector}')", 30_000);

    $initialCount = $page->script('() => ' . TRANSACTION_LIST_ROWS);
    expect($initialCount)->toBeGreaterThan(0);

    // Cancel only resets a local busy flag: no request fires and the table doesn't change
    $page->click($deleteSelector)
        ->click('.swal2-cancel');
    $this->waitUntil($page, "!document.querySelector('.swal2-container')");
    $page->assertScript(TRANSACTION_LIST_ROWS, $initialCount);

    $page->click($deleteSelector)
        ->click('.swal2-confirm');
    $this->waitUntil($page, "!document.querySelector('{$deleteSelector}') && " . TRANSACTION_LIST_ROWS . ' === ' . ($initialCount - 1));

    $page->click('#nav-summary');
    $this->waitUntil($page, "document.querySelector('#tab-summary')");
    $page->click('#nav-transaction-list');
    $this->waitUntil($page, "document.querySelector('#tab-transaction-list table')");

    $page->assertMissing($deleteSelector)
        ->assertScript(TRANSACTION_LIST_ROWS, $initialCount - 1);
})->group('critical');
