<?php

use App\Enums\TransactionType;
use App\Models\AiDocument;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

function scheduledWithdrawal(User $user, $account, $payee): Transaction
{
    $detail = TransactionDetailStandard::query()->create([
        'account_from_id' => $account->id,
        'account_to_id' => $payee->id,
        'amount_from' => 50,
        'amount_to' => 50,
    ]);
    $transaction = new Transaction([
        'date' => '2025-03-10',
        'transaction_type' => TransactionType::WITHDRAWAL->value,
        'reconciled' => false,
        'schedule' => true,
        'config_type' => 'standard',
        'config_id' => $detail->id,
    ]);
    $transaction->user_id = $user->id;
    $transaction->save();
    TransactionItem::query()->create([
        'transaction_id' => $transaction->id,
        'category_id' => Category::factory()->for($user)->create()->id,
        'amount' => 50,
    ]);
    TransactionSchedule::query()->create([
        'transaction_id' => $transaction->id,
        'start_date' => '2025-03-10',
        'next_date' => '2025-03-10',
        'frequency' => 'MONTHLY',
        'interval' => 1,
        'automatic_recording' => false,
    ]);

    return $transaction;
}

function draftDocument(User $user, $account, $payee): AiDocument
{
    return AiDocument::factory()->for($user)->create([
        'processed_transaction_data' => [
            'raw' => ['payee' => 'Shop'],
            'date' => '2025-03-11',
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'config' => [
                'amount_from' => 50,
                'account_from_id' => $account->id,
                'account_to_id' => $payee->id,
            ],
        ],
    ]);
}

it('offers an active schedule whose next date matches the document draft', function () {
    $user = User::factory()->create();
    $account = $this->createAccountEntity($user);
    $payee = $this->createPayeeEntity($user);
    $transaction = scheduledWithdrawal($user, $account, $payee);
    $document = draftDocument($user, $account, $payee);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.documents.checkDuplicates', ['aiDocument' => $document]))
        ->assertOk()
        ->assertJsonCount(1, 'schedules')
        ->assertJsonPath('schedules.0.transaction_id', $transaction->id);
});

it('lists a matching transaction with its match signals and a similar open document, but not the document itself', function () {
    $user = User::factory()->create();
    $account = $this->createAccountEntity($user);
    $payee = $this->createPayeeEntity($user);
    $document = draftDocument($user, $account, $payee);
    $other = draftDocument($user, $account, $payee);
    // A slightly different amount: the same-purchase classifier would list an exact twin under `documents`
    $other->update([
        'status' => 'ready_for_review',
        'processed_transaction_data' => array_replace_recursive($other->processed_transaction_data, ['config' => ['amount_from' => 50.5]]),
    ]);
    $existing = scheduledWithdrawal($user, $account, $payee);
    $existing->update(['schedule' => false, 'date' => '2025-03-11']);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.documents.checkDuplicates', ['aiDocument' => $document]))
        ->assertOk()
        ->assertJsonPath('duplicates.0.id', $existing->id)
        ->assertJsonPath('duplicates.0.matched_on', ['date', 'amount', 'account_from', 'account_to'])
        ->assertJsonCount(1, 'related_documents')
        ->assertJsonPath('related_documents.0.ai_document_id', $other->id);
});

it('finalizes the document when one of its scheduled instances is entered', function () {
    $user = User::factory()->create();
    $account = $this->createAccountEntity($user);
    $payee = $this->createPayeeEntity($user);
    $scheduled = scheduledWithdrawal($user, $account, $payee);
    $document = draftDocument($user, $account, $payee);
    $document->update(['status' => 'ready_for_review']);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.transactions.store-standard'), [
        'action' => 'enter',
        'id' => $scheduled->id,
        'ai_document_id' => $document->id,
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => '2025-03-10',
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
            'amount_from' => 50,
            'amount_to' => 50,
        ],
        'items' => [['amount' => 50, 'category_id' => Category::factory()->for($user)->create(['active' => true])->id]],
    ])->assertOk();

    expect($document->fresh()->status)->toBe('finalized')
        ->and($scheduled->fresh()->transactionSchedule->next_date->toDateString())->not->toBe('2025-03-10');
});
