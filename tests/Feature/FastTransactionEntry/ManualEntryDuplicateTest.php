<?php

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\TransactionOrigin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    AiUserSettings::factory()->enabled()->create(['user_id' => $this->user->id]);
    $this->account = AccountEntity::factory()->asAccount($this->user)->create(['active' => true]);
    $this->payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => true]);
    $this->actingAs($this->user);

    $this->openDocument = fn (string $status = 'ready_for_review', string $amount = '1500.00', ?User $owner = null) => AiDocument::factory()
        ->for($owner ?? $this->user)
        ->withDraft([
            'schema_version' => 2,
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'date' => '2026-03-01',
            'config' => [
                'account_from_id' => $this->account->id,
                'account_to_id' => $this->payee->id,
                'amount_from' => $amount,
                'amount_to' => $amount,
            ],
            'transaction_items' => [],
        ])
        ->create(['status' => $status]);

    $this->entry = fn (array $overrides = []) => $overrides + [
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'account_id' => $this->account->id,
        'payee_id' => $this->payee->id,
        'date' => '2026-03-02',
        'amount' => '1500',
    ];

    $this->storePayload = fn (array $extra = []) => $extra + [
        'action' => 'create',
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => '2026-03-01',
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_from_id' => $this->account->id,
            'account_to_id' => $this->payee->id,
            'amount_from' => 1500,
            'amount_to' => 1500,
        ],
        'items' => [['amount' => 1500, 'category_id' => Category::factory()->for($this->user)->create(['active' => true])->id]],
    ];
});

it('lists matching transactions and open documents for a transaction being entered', function () {
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 1500, '2026-03-01');
    $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 1400, '2026-03-01');
    $held = ($this->openDocument)('awaiting_itemization');
    ($this->openDocument)('ready_for_review', '99.00');
    ($this->openDocument)('finalized');
    ($this->openDocument)('ready_for_review', owner: User::factory()->create());

    $this->postJson(route('api.v1.transactions.duplicate-check'), ($this->entry)())
        ->assertOk()
        ->assertJsonCount(1, 'transactions')
        ->assertJsonPath('transactions.0.id', $transaction->id)
        ->assertJsonPath('transactions.0.amount', '1500')
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.id', $held->id)
        ->assertJsonPath('documents.0.status', 'awaiting_itemization');
});

it('leaves out the transaction being edited', function () {
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 1500, '2026-03-01');

    $this->postJson(route('api.v1.transactions.duplicate-check'), ($this->entry)(['exclude_transaction_id' => $transaction->id]))
        ->assertOk()
        ->assertJsonCount(0, 'transactions');
});

it('does not accept another user\'s account, payee or transaction', function () {
    $foreign = AccountEntity::factory()->asAccount(User::factory()->create())->create();
    $foreignTransaction = $this->createStandardTransaction(
        $other = User::factory()->create(),
        AccountEntity::factory()->asAccount($other)->create()->id,
        AccountEntity::factory()->asPayee($other)->create()->id,
        1500,
        '2026-03-01'
    );

    $this->postJson(route('api.v1.transactions.duplicate-check'), ($this->entry)(['account_id' => $foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('account_id');
    $this->postJson(route('api.v1.transactions.duplicate-check'), ($this->entry)(['exclude_transaction_id' => $foreignTransaction->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('exclude_transaction_id');
});

it('returns nothing for transfers and investments', function () {
    ($this->openDocument)();

    $this->postJson(route('api.v1.transactions.duplicate-check'), ($this->entry)(['transaction_type' => 'transfer']))
        ->assertOk()->assertExactJson(['transactions' => [], 'documents' => []]);
    $this->postJson(route('api.v1.transactions.duplicate-check'), ($this->entry)(['config_type' => 'investment', 'transaction_type' => 'buy']))
        ->assertOk()->assertExactJson(['transactions' => [], 'documents' => []]);
});

it('closes the chosen open document as a duplicate when the transaction is saved', function () {
    $document = ($this->openDocument)('awaiting_itemization');

    $id = $this->postJson(route('api.v1.transactions.store-standard'), ($this->storePayload)(['close_ai_document_id' => $document->id]))
        ->assertOk()
        ->json('transaction.id');

    expect($document->fresh()->status)->toBe('duplicate');
    $origin = TransactionOrigin::sole();
    expect($origin->transaction_id)->toBe($id)
        ->and($origin->relation)->toBe('duplicate_of')
        ->and($origin->origin_id)->toBe($document->id);
});

it('rejects closing a foreign or terminal document, and still saves nothing', function () {
    $foreign = ($this->openDocument)('ready_for_review', owner: User::factory()->create());
    $finalized = ($this->openDocument)('finalized');

    foreach ([$foreign, $finalized] as $document) {
        $this->postJson(route('api.v1.transactions.store-standard'), ($this->storePayload)(['close_ai_document_id' => $document->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('close_ai_document_id');
    }

    expect(TransactionOrigin::count())->toBe(0)
        ->and($foreign->fresh()->status)->toBe('ready_for_review')
        ->and($finalized->fresh()->status)->toBe('finalized');
});

it('rejects closing the document that is being finalized', function () {
    $document = ($this->openDocument)();

    $this->postJson(route('api.v1.transactions.store-standard'), ($this->storePayload)([
        'action' => 'finalize',
        'ai_document_id' => $document->id,
        'close_ai_document_id' => $document->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('close_ai_document_id');
});

it('lists other open documents of the same purchase in the document duplicate check', function () {
    $document = ($this->openDocument)();
    $other = ($this->openDocument)('awaiting_itemization');
    ($this->openDocument)('ready_for_review', '99.00');

    $this->postJson(route('api.v1.documents.checkDuplicates', $document))
        ->assertOk()
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.id', $other->id);
});
