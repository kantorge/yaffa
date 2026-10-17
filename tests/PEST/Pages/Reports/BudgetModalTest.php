<?php

use App\Models\AccountEntity;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);

    $this->category = Category::where('user_id', $this->user->id)->whereNotNull('parent_id')->firstOrFail();
    // Prefer an account in a non-base currency, so the suffix visibly changes
    $baseCurrencyId = $this->user->baseCurrency()->id;
    $accounts = $this->user->accounts()->where('active', true)->with('config.currency')->get();
    $this->account = $accounts->first(fn (AccountEntity $account) => $account->config->currency_id !== $baseCurrencyId)
        ?? $accounts->firstOrFail();
});

function currencySuffix(string $modalId): string
{
    return "document.querySelector('#{$modalId} .input-group-text').textContent.trim()";
}

it('creates a budget with a category and an account, the amount suffix following the account currency', function () {
    $page = visit(route('reports.budgetchart'));
    $this->waitUntil($page, "document.querySelector('#newBudgetModal-account_id')?.tomselect");

    $page->click('#button-new-budget');
    $this->waitUntil($page, "document.querySelector('#newBudgetModal').classList.contains('show')");
    $page->assertScript(currencySuffix('newBudgetModal'), $this->user->baseCurrency()->iso_code);

    $this->chooseTomSelectOption($page, 'newBudgetModal-category_id', $this->category->name, $this->category->full_name);
    $this->chooseTomSelectOption($page, 'newBudgetModal-account_id', $this->account->name, $this->account->name);
    $this->waitUntil($page, currencySuffix('newBudgetModal') . " === '{$this->account->config->currency->iso_code}'");

    $page->type('#newBudgetModal-amount', '123')
        ->type('#newBudgetModal #schedule_start_budget-period', now()->format('Y-m-d'))
        ->click('#newBudgetModal button[type=submit]');
    $this->waitUntil($page, "!document.querySelector('#newBudgetModal').classList.contains('show')");
    $page->assertNoJavaScriptErrors();

    $budget = Budget::where('user_id', $this->user->id)->latest('id')->firstOrFail();
    expect($budget->category_id)->toBe($this->category->id)
        ->and($budget->account_id)->toBe($this->account->id)
        ->and($budget->amount->getAmount()->isEqualTo(123))->toBeTrue();
});

it('loads the category and account into the edit modal', function () {
    $budget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'account_id' => $this->account->id,
        'transaction_type' => 'withdrawal',
        'frequency' => 'MONTHLY',
        'interval' => 1,
        'start_date' => now()->startOfMonth(),
    ]);

    $page = visit(route('reports.budgetchart', ['categories' => [$this->category->id]]));
    $this->waitUntil($page, "document.querySelector('[data-edit-budget=\"{$budget->id}\"]')", 15_000);
    $page->click("[data-edit-budget=\"{$budget->id}\"]");
    $this->waitUntil($page, "document.querySelector('#editBudgetModal').classList.contains('show')");

    $this->assertTomSelectValues($page, 'editBudgetModal-category_id', [$this->category->id]);
    $this->assertTomSelectValues($page, 'editBudgetModal-account_id', [$this->account->id]);
    $page->assertSeeIn('#editBudgetModal-category_id + .ts-wrapper .item', $this->category->full_name)
        ->assertSeeIn('#editBudgetModal-account_id + .ts-wrapper .item', $this->account->name)
        ->assertScript(currencySuffix('editBudgetModal'), $this->account->config->currency->iso_code)
        ->assertNoJavaScriptErrors();
});
