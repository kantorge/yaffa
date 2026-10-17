<?php

use App\Models\User;
use Illuminate\Support\Str;

/*
 * Converted from the Dusk test of the same name (tests/Browser/Pages/Payees).
 */

it('adds a new payee from the list modal and filters it without reloading', function () {
    $user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($user);
    $category = $user->categories()->where('active', true)->firstOrFail();
    $payeeName = 'Modal Payee ' . Str::uuid();
    $rows = 'window.table.rows({ search: "applied" })';
    $hasPayee = fn (string $condition = 'true') => "{$rows}.data().toArray().some((row) => row.name === '{$payeeName}' && {$condition})";

    $page = visit(route('account-entity.index', ['type' => 'payee']));
    $this->waitUntil($page, 'window.table !== undefined');
    $page->assertPresent('[dusk="table-payees"]')
        ->script('() => { window.__noReload = true; }');
    $initialTotalCount = $page->script("() => {$rows}.count()");

    $page->type('[dusk="input-table-filter-search"]', 'zzzz-no-payee-match');
    $this->waitUntil($page, "{$rows}.count() === 0");

    $page->click('[dusk="button-new-payee"]');
    $this->waitUntil($page, "document.querySelector('#newPayeeModal').classList.contains('show')");
    $page->type('#newPayeeModal-name', $payeeName);
    $this->chooseTomSelectOption($page, 'newPayeeModal-category_id', $category->name, $category->full_name);
    $page->click('#newPayeeModal button[type="submit"]');
    $this->waitUntil($page, "!document.querySelector('#newPayeeModal').classList.contains('show')");

    // Saving resets the search, so the new payee is listed with all the others
    $this->waitUntil($page, "{$rows}.count() === " . ($initialTotalCount + 1));
    $this->waitUntil($page, $hasPayee());
    $page->assertValue('[dusk="input-table-filter-search"]', '');

    $page->type('[dusk="input-table-filter-search"]', $payeeName);
    $this->waitUntil($page, "{$rows}.count() === 1");

    $page->click('label[for=table_filter_active_yes]')
        ->click('label[for=table_filter_default_category_yes]');
    $this->waitUntil($page, "{$rows}.count() === 1");

    $page->assertScript($hasPayee('row.has_default_category === true && row.active === true'), true)
        ->assertScript('window.__noReload', true);
})->group('extended');
