<?php

use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
    $this->account = $this->user->accounts()->firstOrFail();
});

function accountFilterShows(int $accountId, string $name): string
{
    return "(() => {
        const ts = document.querySelector('#' + (document.querySelector('#cashflowAccount') ? 'cashflowAccount' : 'accountList')).tomselect;
        return ts?.getValue() === '{$accountId}' && ts.getItem('{$accountId}')?.textContent.trim() === " . json_encode($name) . ';
    })()';
}

function requested(string $path, int $accountId): string
{
    return "performance.getEntriesByType('resource').some((entry) => entry.name.includes('{$path}') && entry.name.includes('accountEntity={$accountId}'))";
}

it('presets the cashflow account filter from the URL, loads its data, and clearing updates the URL', function () {
    $page = visit(route('reports.cashflow', ['accountEntity' => $this->account->id]));

    $this->waitUntil($page, accountFilterShows($this->account->id, $this->account->name));
    $this->waitUntil($page, requested('/api/v1/reports/cashflow', $this->account->id));

    $this->clearTomSelect($page, 'cashflowAccount');
    $page->assertQueryStringMissing('accountEntity')
        ->assertNoJavaScriptErrors();
});

it('presets the budget chart account filter from the URL, loads its data, and clearing updates the URL', function () {
    $category = Category::where('user_id', $this->user->id)->firstOrFail();

    $page = visit(route('reports.budgetchart', ['accountEntity' => $this->account->id, 'categories' => [$category->id]]));

    $this->waitUntil($page, accountFilterShows($this->account->id, $this->account->name));
    $this->waitUntil($page, requested('/api/v1/reports/budget-chart', $this->account->id));

    // The account select is only enabled for the "selected account" scope
    $page->click('label[for=table_filter_account_scope_selected]');
    $this->clearTomSelect($page, 'accountList');
    $page->assertQueryStringMissing('accountEntity')
        ->assertNoJavaScriptErrors();
});
