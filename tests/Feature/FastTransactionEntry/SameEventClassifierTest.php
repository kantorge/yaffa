<?php

use App\Enums\SameEventOutcome;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;
use App\Services\SameEventClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    AiUserSettings::factory()->enabled()->create(['user_id' => $this->user->id]);
    $this->account = AccountEntity::factory()->asAccount($this->user)->create(['active' => true]);
    $this->payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => true]);

    $this->draft = fn (string $amount = '1500.00', string $date = '2026-03-01', array $raw = []) => [
        'schema_version' => 2,
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'date' => $date,
        'config' => [
            'account_from_id' => $this->account->id,
            'account_to_id' => $this->payee->id,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ],
        'transaction_items' => [],
        'raw' => $raw,
    ];

    $this->document = fn (string $status, ?string $kind, array $raw = [], array $attributes = [], string $amount = '1500.00', string $date = '2026-03-01') => AiDocument::factory()
        ->for($this->user)
        ->withDraft(($this->draft)($amount, $date, $raw))
        ->create(['status' => $status, 'document_kind' => $kind] + $attributes);

    $this->transaction = fn (float $amount = 1500, string $date = '2026-03-01', ?AiDocument $linked = null) => tap(
        $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, $amount, $date),
        fn (Transaction $transaction) => $linked && $transaction->forceFill(['ai_document_id' => $linked->id])->save()
    );

    $this->classify = fn (AiDocument $document) => app(SameEventClassifier::class)->classify($document->fresh())->outcome;
});

it('treats the same content again as an exact repeat', function () {
    ($this->document)('finalized', 'receipt', attributes: ['content_hash' => str_repeat('a', 64)]);
    $incoming = ($this->document)('processing', 'receipt', attributes: ['content_hash' => str_repeat('a', 64)]);

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::ExactRepeat);
});

it('does not treat a dismissed or failed document with the same content as a repeat', function () {
    ($this->document)('dismissed', 'receipt', attributes: ['content_hash' => str_repeat('a', 64)]);
    $incoming = ($this->document)('processing', 'receipt', attributes: ['content_hash' => str_repeat('a', 64)]);

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::None);
});

it('keeps two parking notifications of one day with different times separate', function () {
    $first = ($this->document)('finalized', 'bank_notification', ['transaction_time' => '10:00']);
    ($this->transaction)(linked: $first);
    $second = ($this->document)('processing', 'bank_notification', ['transaction_time' => '12:00']);

    expect(($this->classify)($second))->toBe(SameEventOutcome::None);
});

it('does not let a transaction absorb a second notification', function () {
    $first = ($this->document)('finalized', 'bank_notification');
    ($this->transaction)(linked: $first);
    $second = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($second))->toBe(SameEventOutcome::None);
});

it('treats a notification and a receipt without times as the same event', function () {
    $notification = ($this->document)('finalized', 'bank_notification');
    $transaction = ($this->transaction)(linked: $notification);
    $receipt = ($this->document)('processing', 'receipt');

    $result = app(SameEventClassifier::class)->classify($receipt->fresh());

    expect($result->outcome)->toBe(SameEventOutcome::SameEventTransaction)
        ->and($result->transaction->is($transaction))->toBeTrue();
});

it('treats a notification as the same event as a purchase entered by hand', function () {
    ($this->transaction)();
    $notification = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($notification))->toBe(SameEventOutcome::SameEventTransaction);
});

it('finds the same event through a duplicate_of origin', function () {
    $transaction = ($this->transaction)();
    $closed = ($this->document)('duplicate', 'bank_notification');
    TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_DUPLICATE_OF, $closed);
    $second = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($second))->toBe(SameEventOutcome::None);
});

it('treats an equal bank reference as the same event', function () {
    ($this->document)('ready_for_review', 'other', ['bank_reference' => 'AB-123']);
    $incoming = ($this->document)('processing', 'other', ['bank_reference' => 'ab-123 ']);

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::SameEventDocument);
});

it('treats timestamps within the window as the same event, and beyond it as separate', function () {
    ($this->document)('ready_for_review', 'bank_notification', ['transaction_time' => '10:00']);

    $near = ($this->document)('processing', 'bank_notification', ['transaction_time' => '10:05']);
    $far = ($this->document)('processing', 'bank_notification', ['transaction_time' => '10:30']);

    expect(($this->classify)($near))->toBe(SameEventOutcome::SameEventDocument)
        ->and(($this->classify)($far))->toBe(SameEventOutcome::None);
});

it('sends a same-kind candidate without any signal to review as a candidate', function () {
    ($this->document)('ready_for_review', 'bank_notification');
    $incoming = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::Candidate);
});

it('reports an amount that is only close as a near match', function () {
    ($this->transaction)(1500);
    $incoming = ($this->document)('processing', 'bank_notification', amount: '1425.00');

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::NearMatch);
});

it('ignores a transaction outside the date window', function () {
    ($this->transaction)(1500, '2026-02-20');
    $incoming = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::None);
});

it('ignores another user\'s transactions and documents', function () {
    $other = User::factory()->create();
    $account = AccountEntity::factory()->asAccount($other)->create(['active' => true]);
    $payee = AccountEntity::factory()->asPayee($other)->create(['active' => true]);
    $this->createStandardTransaction($other, $account->id, $payee->id, 1500, '2026-03-01');
    $incoming = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::None);
});

it('needs the same payee', function () {
    $this->createStandardTransaction(
        $this->user,
        $this->account->id,
        AccountEntity::factory()->asPayee($this->user)->create(['active' => true])->id,
        1500,
        '2026-03-01'
    );
    $incoming = ($this->document)('processing', 'bank_notification');

    expect(($this->classify)($incoming))->toBe(SameEventOutcome::None);
});

it('reads a draft with float amounts through normalize', function () {
    $transaction = ($this->transaction)(12.5);
    $document = ($this->document)('processing', 'bank_notification');
    $draft = $document->processed_transaction_data;
    $draft['config']['amount_from'] = 12.5;
    $document->update(['processed_transaction_data' => $draft]);

    expect(($this->classify)($document))->toBe(SameEventOutcome::SameEventTransaction);
});
