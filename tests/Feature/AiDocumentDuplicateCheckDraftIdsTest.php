<?php

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const DRAFT_DATE = '2026-05-15';
const DRAFT_AMOUNT = 42.5;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = AccountEntity::factory()->asAccount($this->user)->create();
    $this->payee = AccountEntity::factory()->asPayee($this->user)->create();
    $this->otherAccount = AccountEntity::factory()->asAccount($this->user)->create();
    $this->otherPayee = AccountEntity::factory()->asPayee($this->user)->create();
    Sanctum::actingAs($this->user, ['*']);
});

function duplicateCheckWithdrawal(User $user, AccountEntity $account, AccountEntity $payee): Transaction
{
    $transaction = Transaction::factory()->withdrawal($user)->create([
        'date' => DRAFT_DATE,
        'config_id' => TransactionDetailStandard::factory()->withdrawal($user)->create([
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ])->id,
    ]);

    // The factory adds random items; pin the total so every candidate ties on amount.
    $transaction->transactionItems()->delete();
    $transaction->transactionItems()->create(TransactionItem::factory()->withUser($user)->raw(['amount' => DRAFT_AMOUNT]));

    return $transaction;
}

function withdrawalDraft(mixed $accountFromId, mixed $accountToId): array
{
    return [
        'raw' => ['date' => DRAFT_DATE, 'amount' => DRAFT_AMOUNT, 'account' => 'Checking', 'payee' => 'Shop'],
        'date' => DRAFT_DATE,
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'config' => [
            'amount_from' => DRAFT_AMOUNT,
            'amount_to' => DRAFT_AMOUNT,
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
        ],
        'transaction_items' => [],
    ];
}

function checkDraftDuplicates(User $user, array $draft): TestResponse
{
    $document = AiDocument::factory()->for($user)->create(['processed_transaction_data' => $draft]);

    return test()->postJson(route('api.v1.documents.checkDuplicates', ['aiDocument' => $document]));
}

function setDuplicateThreshold(User $user, float $threshold): void
{
    AiUserSettings::factory()->for($user)->create(['duplicate_similarity_threshold' => $threshold]);
}

it('ranks a transaction on the draft account and payee above a same-amount one elsewhere', function () {
    $match = duplicateCheckWithdrawal($this->user, $this->account, $this->payee);
    $other = duplicateCheckWithdrawal($this->user, $this->otherAccount, $this->otherPayee);

    $duplicates = checkDraftDuplicates($this->user, withdrawalDraft($this->account->id, $this->payee->id))
        ->assertOk()
        ->json('duplicates');

    expect(array_column($duplicates, 'id'))->toBe([$match->id, $other->id])
        ->and($duplicates[0]['similarity'])->toBeGreaterThan($duplicates[1]['similarity']);
});

it('does not return a transaction on another payee once date and amount alone fall below the threshold', function () {
    // Date (1) + exact amount (2) out of 5 points = 0.6, which must not pass.
    setDuplicateThreshold($this->user, 0.6);
    $match = duplicateCheckWithdrawal($this->user, $this->account, $this->payee);
    duplicateCheckWithdrawal($this->user, $this->otherAccount, $this->otherPayee);

    checkDraftDuplicates($this->user, withdrawalDraft($this->account->id, $this->payee->id))
        ->assertOk()
        ->assertJsonCount(1, 'duplicates')
        ->assertJsonPath('duplicates.0.id', $match->id);
});

it('does not return an investment transaction for a standard draft', function () {
    setDuplicateThreshold($this->user, 0.0);
    Transaction::factory()->buy($this->user)->create(['date' => DRAFT_DATE]);

    checkDraftDuplicates($this->user, withdrawalDraft($this->account->id, $this->payee->id))
        ->assertOk()
        ->assertJsonCount(0, 'duplicates');
});

it('matches account IDs stored as strings in the draft', function () {
    $match = duplicateCheckWithdrawal($this->user, $this->account, $this->payee);

    checkDraftDuplicates($this->user, withdrawalDraft((string) $this->account->id, (string) $this->payee->id))
        ->assertOk()
        ->assertJsonPath('duplicates.0.id', $match->id)
        ->assertJsonPath('duplicates.0.similarity', 1);
});

it('still checks a legacy draft that only has raw date and amount', function () {
    $match = duplicateCheckWithdrawal($this->user, $this->account, $this->payee);

    checkDraftDuplicates($this->user, ['raw' => ['date' => DRAFT_DATE, 'amount' => DRAFT_AMOUNT]])
        ->assertOk()
        ->assertJsonPath('duplicates.0.id', $match->id);
});

it('flattens an investment draft without an amount', function () {
    $data = app(DuplicateDetectionService::class)->matchDataFromDraft([
        'raw' => ['date' => DRAFT_DATE, 'amount' => 100],
        'date' => DRAFT_DATE,
        'config_type' => 'investment',
        'transaction_type' => 'buy',
        'config' => ['account_id' => '7', 'investment_id' => 3, 'quantity' => 2, 'price' => 50],
    ]);

    expect($data)->toBe([
        'date' => DRAFT_DATE,
        'config_type' => 'investment',
        'transaction_type' => 'buy',
        'account_id' => 7,
        'investment_id' => 3,
    ]);
});
