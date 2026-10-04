<?php

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Category;
use App\Models\TransactionOrigin;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('warns about an open document while entering a purchase by hand and closes it on save', function () {
    $account = AccountEntity::where('user_id', $this->user->id)->where('name', 'Cash account EUR')->firstOrFail();
    $payee = AccountEntity::where('user_id', $this->user->id)->where('name', 'Auchan')->firstOrFail();

    $document = AiDocument::factory()->for($this->user)->readyForReview()->withDraft([
        'schema_version' => 2,
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'date' => now()->toDateString(),
        'config' => [
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
            'amount_from' => '100',
            'amount_to' => '100',
        ],
        'transaction_items' => [],
        'raw' => ['document_kind' => 'bank_notification'],
    ])->create(['document_kind' => 'bank_notification']);

    $page = visit(route('transaction.create', ['type' => 'standard']));
    $this->waitUntil($page, "document.querySelector('#account_from')?.tomselect && document.querySelector('#account_to')?.tomselect");

    $this->chooseTomSelectOption($page, 'account_from', 'Cash account EUR', 'Cash account EUR');
    $this->chooseTomSelectOption($page, 'account_to', 'Auchan', 'Auchan');
    $page->type('#transaction_amount_from', '100');

    $categorySelectId = $this->addTransactionItem($page);
    $this->chooseTomSelectOption($page, $categorySelectId, 'Groceries', Category::firstWhere('name', 'Groceries')->full_name);
    $page->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100');

    // The warning appears, and saving stays possible
    $this->waitUntil($page, "document.querySelector('[dusk=\"duplicate-warning\"]')");
    $page->assertSeeIn('[dusk="duplicate-warning"]', 'may already be recorded')
        ->assertEnabled('#transactionFormStandard-Save')
        ->click("#close-ai-document-{$document->id}")
        ->click('#transactionFormStandard-Save');

    $this->waitUntil($page, "document.querySelector('#BootstrapNotificationContainer')?.textContent.includes('Transaction added')");

    expect($document->fresh()->status)->toBe('duplicate')
        ->and(TransactionOrigin::where('origin_id', $document->id)->where('relation', 'duplicate_of')->exists())->toBeTrue();
})->group('critical');
