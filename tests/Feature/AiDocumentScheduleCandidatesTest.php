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

it('offers an active schedule whose next date matches the document draft', function () {
    $user = User::factory()->create();
    $account = $this->createAccountEntity($user);
    $payee = $this->createPayeeEntity($user);

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

    $document = AiDocument::factory()->for($user)->create([
        'processed_transaction_data' => [
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

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.documents.checkDuplicates', ['aiDocument' => $document]))
        ->assertOk()
        ->assertJsonCount(1, 'schedules')
        ->assertJsonPath('schedules.0.transaction_id', $transaction->id);
});
