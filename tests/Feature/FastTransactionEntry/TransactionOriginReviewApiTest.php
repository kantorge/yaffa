<?php

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->origin = function (string $relation = 'created', ?User $owner = null, ?AiDocument $document = null) {
        $owner ??= $this->user;
        $transaction = $this->createStandardTransaction(
            $owner,
            AccountEntity::factory()->asAccount($owner)->create(['active' => true])->id,
            AccountEntity::factory()->asPayee($owner)->create()->id,
            10,
            now(),
        );
        $document ??= AiDocument::factory()->for($owner)->autoRecorded()->create();

        return TransactionOrigin::record($owner, $transaction, $relation, $document, 'because');
    };
});

it('lists unreviewed created origins and filters by relation and review state', function () {
    $created = ($this->origin)();
    $reviewed = ($this->origin)();
    $reviewed->update(['reviewed_at' => now()]);
    $conflict = ($this->origin)('conflicts_with');
    ($this->origin)('created', User::factory()->create());

    $ids = fn (string $query) => collect($this->getJson(route('api.v1.transaction-origins.index') . $query)->assertOk()->json())->pluck('id')->all();

    expect($ids('?relation=created&reviewed=0'))->toBe([$created->id])
        ->and($ids('?relation=created&reviewed=1'))->toBe([$reviewed->id])
        ->and($ids('?relation=conflicts_with'))->toBe([$conflict->id])
        ->and($ids(''))->toHaveCount(3);

    $row = $this->getJson(route('api.v1.transaction-origins.index') . '?relation=created&reviewed=0')->json(0);
    expect($row['decision_reason'])->toBe('because')
        ->and($row['transaction']['id'])->toBe($created->transaction_id)
        ->and($row['ai_document']['id'])->toBe($created->origin_id);
});

it('confirms one origin and bulk confirms several, ignoring foreign ones', function () {
    $one = ($this->origin)();
    $a = ($this->origin)();
    $b = ($this->origin)();
    $foreign = ($this->origin)('created', User::factory()->create());

    $this->postJson(route('api.v1.transaction-origins.review', $one))->assertOk();
    expect($one->fresh()->reviewed_at)->not->toBeNull();

    $this->postJson(route('api.v1.transaction-origins.review-many'), ['ids' => [$a->id, $b->id, $foreign->id]])
        ->assertOk()->assertJson(['reviewed' => 2]);
    expect($a->fresh()->reviewed_at)->not->toBeNull()
        ->and($foreign->fresh()->reviewed_at)->toBeNull();

    $this->postJson(route('api.v1.transaction-origins.review', $foreign))->assertForbidden();
});

it('marks the created origin reviewed when the transaction is reconciled, from the endpoint and from an edit', function () {
    $viaEndpoint = ($this->origin)();
    $viaEdit = ($this->origin)();
    $unrelated = ($this->origin)('duplicate_of');

    $this->patchJson(route('api.v1.transactions.reconcile', $viaEndpoint->transaction_id), ['reconciled' => true])->assertOk();
    expect($viaEndpoint->fresh()->reviewed_at)->not->toBeNull();

    // Any save that flips reconciled counts, e.g. the update endpoint
    Transaction::find($viaEdit->transaction_id)->update(['reconciled' => true]);
    expect($viaEdit->fresh()->reviewed_at)->not->toBeNull()
        ->and($unrelated->fresh()->reviewed_at)->toBeNull();

    // Unreconciling does not un-review
    $this->patchJson(route('api.v1.transactions.reconcile', $viaEndpoint->transaction_id), ['reconciled' => false])->assertOk();
    expect($viaEndpoint->fresh()->reviewed_at)->not->toBeNull();
});

it('denies reviewing an origin through the policy when the user does not own it', function () {
    $foreign = ($this->origin)('created', User::factory()->create());

    expect($this->user->can('update', $foreign))->toBeFalse()
        ->and($this->user->can('update', ($this->origin)()))->toBeTrue();

    // Even when only foreign IDs are sent, nothing is reviewed
    $this->postJson(route('api.v1.transaction-origins.review-many'), ['ids' => [$foreign->id]])
        ->assertOk()->assertJson(['reviewed' => 0]);
    expect($foreign->fresh()->reviewed_at)->toBeNull();
});

it('lists origins with a query count that stays flat as the number of origins', function () {
    // Two to begin with, as an eager load of an empty relation costs no query
    ($this->origin)();
    ($this->origin)();
    DB::enableQueryLog();
    $this->getJson(route('api.v1.transaction-origins.index'))->assertOk();
    $one = count(DB::getQueryLog());

    foreach (range(1, 3) as $_) {
        ($this->origin)();
    }
    DB::flushQueryLog();
    $this->getJson(route('api.v1.transaction-origins.index'))->assertOk()->assertJsonCount(5);

    // An eager load of a relation that is empty for the first rows may add one query; an N+1 adds several per row
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($one + 1);
});
