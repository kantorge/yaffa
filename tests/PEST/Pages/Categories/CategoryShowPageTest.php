<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);

    // The category used by the most transaction items, so the list is never empty
    $this->category = $this->user->categories()
        ->withCount('transactionItem')
        ->orderByDesc('transaction_item_count')
        ->firstOrFail();
});

const CATEGORY_LIST_ROWS = "$('#tab-transaction-list table').DataTable().rows({ search: 'applied' }).count()";

it('shows the overview and the report tabs', function () {
    $page = visit(route('categories.show', $this->category));
    $this->waitUntil($page, "document.querySelector('#categoryOverviewCard')");

    $page->assertSee($this->category->name)
        ->assertPresent('#categorySchedulesCard')
        ->assertPresent('#nav-summary')
        ->assertPresent('#nav-transaction-list')
        ->assertPresent('#nav-monthly-breakdown')
        ->assertNoJavaScriptErrors();
});

it('lists the category\'s transactions once the date range is cleared', function () {
    $page = visit(route('categories.show', $this->category));
    $this->waitUntil($page, "document.querySelector('#dateRangeFilterClear')");

    $page->click('#dateRangeFilterClear')
        ->click('#nav-transaction-list');
    $this->waitUntil($page, CATEGORY_LIST_ROWS . ' > 0');

    $page->assertNoJavaScriptErrors();
});

it('links the category list names to the show page', function () {
    $page = visit(route('categories.index'));
    $this->waitUntil($page, 'window.table !== undefined');

    $page->assertPresent('#table a[href="' . route('categories.show', $this->category) . '"]');
});
