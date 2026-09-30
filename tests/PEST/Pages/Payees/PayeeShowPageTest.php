<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);

    // The payee with the most standard transactions, so the list is never empty
    $this->payee = $this->user->payees()
        ->withCount(['transactionsStandardFrom', 'transactionsStandardTo'])
        ->get()
        ->sortByDesc(fn ($payee) => $payee->transactions_standard_from_count + $payee->transactions_standard_to_count)
        ->firstOrFail();
});

const PAYEE_LIST_ROWS = "$('#tab-transaction-list table').DataTable().rows({ search: 'applied' }).count()";

it('shows the overview and the report tabs', function () {
    $page = visit(route('account-entity.show', $this->payee));
    $this->waitUntil($page, "document.querySelector('#payeeOverviewCard')");

    $page->assertSee($this->payee->name)
        ->assertPresent('#payeeSchedulesCard')
        ->assertPresent('#nav-summary')
        ->assertPresent('#nav-transaction-list')
        ->assertPresent('#nav-monthly-breakdown')
        ->assertNoJavaScriptErrors();
});

it('lists only this payee\'s transactions once all transactions are loaded', function () {
    $page = visit(route('account-entity.show', $this->payee));
    $this->waitUntil($page, "document.querySelector('#loadAllTransactionsButton')");

    $page->click('#loadAllTransactionsButton')
        ->click('#nav-transaction-list');
    $this->waitUntil($page, "!document.querySelector('#loadAllTransactionsButton')");
    $this->waitUntil($page, PAYEE_LIST_ROWS . ' === window.overview.count');

    $page->assertNoJavaScriptErrors();
});

it('links the payee list names to the show page', function () {
    $page = visit(route('account-entity.index', ['type' => 'payee']));
    $this->waitUntil($page, 'window.table !== undefined');

    $page->assertPresent('#table a[href="' . route('account-entity.show', $this->payee) . '"]');
});

it('forbids showing another user\'s payee', function () {
    $other = User::where('id', '!=', $this->user->id)->first();
    if ($other === null) {
        $this->markTestSkipped('No second user in the demo data');
    }
    $foreign = $other->payees()->first();
    if ($foreign === null) {
        $this->markTestSkipped('Second user has no payee');
    }

    $this->get(route('account-entity.show', $foreign))->assertForbidden();
});
