<?php

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;

/*
 * Locks in the investment show page's transaction history card, before its table is replaced by a shared one.
 */

const HISTORY_CARD = "[...document.querySelectorAll('.card')].find((card) => card.querySelector('.card-title')?.textContent.trim() === 'Transaction history')";

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
    $this->investment = Investment::where('name', 'Test investment USD')->firstOrFail();

    $this->buy = fn (string $date, float $quantity) => Transaction::factory()
        ->for($this->user)
        ->buy($this->user, [
            'account_id' => AccountEntity::where('name', 'Investment account USD')->firstOrFail()->id,
            'investment_id' => $this->investment->id,
            'price' => 1.5,
            'quantity' => $quantity,
            'commission' => 0,
            'tax' => 0,
        ])
        ->create(['date' => $date]);
});

it('renders the investment transactions in the history card and deletes one after confirmation', function () {
    $transaction = ($this->buy)('2019-03-10', 7.25);
    $deleteButton = "button.data-delete[data-id=\"{$transaction->id}\"]";

    $page = visit(route('investments.show', $this->investment));
    $this->waitUntil($page, '!!(' . HISTORY_CARD . "?.querySelector('{$deleteButton}'))", 30_000);

    // The row shows the transaction's own data
    $rowText = $page->script('() => (' . HISTORY_CARD . ")?.querySelector('{$deleteButton}').closest('tr').textContent");
    expect($rowText)->toContain('2019')->toContain('Buy')->toContain('7.25');

    $rows = '(' . HISTORY_CARD . ")?.querySelectorAll('tbody tr').length";
    $initialRows = $page->script("() => {$rows}");

    // Cancelling changes nothing
    $page->script('() => (' . HISTORY_CARD . ")?.querySelector('{$deleteButton}').click()");
    $this->waitUntil($page, "document.querySelector('.swal2-cancel')");
    $page->click('.swal2-cancel');
    $this->waitUntil($page, "!document.querySelector('.swal2-container')");
    $page->assertScript("{$rows}", $initialRows);

    // Confirming removes the row
    $page->script('() => (' . HISTORY_CARD . ")?.querySelector('{$deleteButton}').click()");
    $this->confirmSwal($page);
    $this->waitUntil($page, "!(" . HISTORY_CARD . ")?.querySelector('{$deleteButton}')");

    expect(Transaction::find($transaction->id))->toBeNull();
})->group('critical');
