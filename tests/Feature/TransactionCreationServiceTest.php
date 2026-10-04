<?php

use App\Events\TransactionCreated;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Services\TransactionCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches TransactionCreated when creating a transaction through the service', function () {
    $user = User::factory()->create();
    $account = AccountEntity::factory()->asAccount($user)->create(['active' => true]);
    $payee = AccountEntity::factory()->asPayee($user)->create(['active' => true]);
    $category = Category::factory()->for($user)->create(['active' => true]);

    Event::fake([TransactionCreated::class]);

    ['transaction' => $transaction, 'category_learning_summary' => $summary] = app(TransactionCreationService::class)->create('standard', [
        'action' => 'create',
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => '2026-10-01',
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
            'amount_from' => '8',
            'amount_to' => '8',
        ],
        'items' => [
            ['amount' => '8', 'category_id' => $category->id],
        ],
    ], $user);

    expect($transaction->exists)->toBeTrue()
        ->and($transaction->user_id)->toBe($user->id)
        ->and($transaction->transactionItems)->toHaveCount(1)
        ->and($summary)->toBeNull();

    Event::assertDispatchedTimes(TransactionCreated::class, 1);
    Event::assertDispatched(TransactionCreated::class, fn (TransactionCreated $event) => $event->transaction->is($transaction));
});

it('never attaches another user\'s tag to an item, but still creates new tags for the owner', function () {
    $user = User::factory()->create();
    $account = AccountEntity::factory()->asAccount($user)->create(['active' => true]);
    $payee = AccountEntity::factory()->asPayee($user)->create(['active' => true]);
    $category = Category::factory()->for($user)->create(['active' => true]);
    $ownTag = Tag::factory()->for($user)->create();
    $foreignTag = Tag::factory()->for(User::factory())->create();

    ['transaction' => $transaction] = app(TransactionCreationService::class)->create('standard', [
        'action' => 'create',
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => '2026-10-01',
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
            'amount_from' => '8',
            'amount_to' => '8',
        ],
        'items' => [
            ['amount' => '8', 'category_id' => $category->id, 'tags' => [$ownTag->id, $foreignTag->id, 'Groceries']],
        ],
    ], $user);

    $attachedTags = $transaction->transactionItems()->first()->tags;

    expect($attachedTags->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($attachedTags->pluck('id'))->toContain($ownTag->id)
        ->not->toContain($foreignTag->id)
        ->and($attachedTags->pluck('name'))->toContain('Groceries')
        ->and($foreignTag->fresh()->user_id)->not->toBe($user->id);
});
