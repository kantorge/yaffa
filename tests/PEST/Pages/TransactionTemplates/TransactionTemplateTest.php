<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('records a transaction from a featured template on the dashboard without entering anything', function () {
    $account = $this->user->accounts()->orderBy('id')->first();
    $payee = AccountEntity::where('user_id', $this->user->id)->where('name', 'Auchan')->firstOrFail();
    $category = Category::where('user_id', $this->user->id)->where('name', 'Groceries')->firstOrFail();

    $template = TransactionTemplate::factory()->featured()->for($this->user)->create([
        'name' => 'Weekly shop',
        'payee_id' => $payee->id,
        'draft' => [
            'schema_version' => 2,
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'config' => [
                'account_from_id' => $account->id,
                'account_to_id' => $payee->id,
                'amount_from' => '100',
                'amount_to' => '100',
            ],
            'transaction_items' => [['category_id' => $category->id, 'amount' => '100']],
        ],
    ]);

    $page = visit(route('home'));

    $this->waitUntil($page, "document.querySelector('[data-testid=\"use-template-{$template->id}\"]')");
    $page->click("[data-testid=\"use-template-{$template->id}\"]");

    $this->waitUntil($page, "document.querySelector('#modal-transaction-form-standard').classList.contains('show') && document.querySelector('#transactionFormStandard').dataset.loaded === 'true'");

    // Prefilled, with today's date
    $this->assertTomSelectValues($page, 'account_from', [$account->id]);
    $this->assertTomSelectValues($page, 'account_to', [$payee->id]);
    $page->assertValue('#transaction_amount_from', '100')
        ->assertValue('#standard-date', now()->toDateString());

    $page->click('#transactionFormStandard-Save');
    $this->waitUntil($page, "!document.querySelector('#modal-transaction-form-standard').classList.contains('show')");
    $this->waitForTextIn($page, '.toast-container .toast.bg-success.show', 'Transaction added');

    $transaction = Transaction::orderByDesc('id')->first();
    expect($transaction->date->toDateString())->toBe(now()->toDateString())
        ->and($transaction->config->amount_from->getAmount()->toFloat())->toEqual(100)
        ->and($template->fresh()->use_count)->toBe(1);
})->group('critical');

it('saves an existing transaction as a template without its date', function () {
    $transaction = Transaction::where('user_id', $this->user->id)
        ->where('config_type', 'standard')
        ->where('transaction_type', 'withdrawal')
        ->where('schedule', false)
        ->orderByDesc('id')
        ->firstOrFail();

    $page = visit(route('transaction.open', ['transaction' => $transaction->id, 'action' => 'template']));

    $this->waitUntil($page, "document.querySelector('#transactionFormStandard').dataset.loaded === 'true'");
    $page->assertNotPresent('#standard-date')
        ->type('#template-name', 'Saved from a transaction')
        ->click('#transactionFormStandard-Save');

    $this->waitUntil($page, "location.pathname.endsWith('/transaction-templates')");

    $template = TransactionTemplate::where('user_id', $this->user->id)->where('name', 'Saved from a transaction')->firstOrFail();
    expect($template->draft)->not->toHaveKey('date')
        ->and($template->draft['transaction_type'])->toBe('withdrawal')
        ->and($template->draft['config']['account_from_id'])->toBe($transaction->config->account_from_id)
        ->and($template->draft['transaction_items'])->not->toBeEmpty();
})->group('critical');
