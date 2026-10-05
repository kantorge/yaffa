<?php

use App\Enums\TransactionType;
use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Jobs\RecalculatePayeeProfile;
use App\Models\Category;
use App\Models\PayeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = $this->createAccountEntity($this->user);
    $this->payee = $this->createPayeeEntity($this->user, ['active' => true]);
    $this->category = Category::factory()->for($this->user)->create(['active' => true]);
});

it('stores the history metrics of a payee', function () {
    foreach ([10, 10, 10, 20] as $i => $amount) {
        $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, $amount, now()->subDays($i), categoryId: $this->category->id);
    }

    $profile = app(App\Services\PayeeProfileService::class)->calculate($this->payee);

    expect($profile->sample_size)->toBe(4)
        ->and($profile->dominant_category_id)->toBe($this->category->id)
        ->and($profile->single_item_dominant_count)->toBe(4)
        ->and((float) $profile->dominant_share)->toBe(1.0)
        ->and((float) $profile->wilson_lower)->toBe(round(App\Services\PayeeProfileService::wilsonLowerBound(4, 4), 4))
        ->and((float) $profile->amount_median)->toBe(10.0)
        ->and((float) $profile->amount_min)->toBe(10.0)
        ->and((float) $profile->amount_max)->toBe(20.0)
        ->and((float) $profile->amount_mode_share)->toBe(0.75)
        ->and($profile->known_amounts)->toBe(['10.0000', '20.0000'])
        ->and($profile->typical_account_ids)->toBe([$this->account->id])
        ->and((float) $profile->multi_item_share)->toBe(0.0);
});

it('calculates an empty profile for a payee without transactions', function () {
    $profile = app(App\Services\PayeeProfileService::class)->calculate($this->payee);

    expect($profile->sample_size)->toBe(0)
        ->and($profile->dominant_category_id)->toBeNull()
        ->and($profile->amount_median)->toBeNull()
        ->and($profile->known_amounts)->toBe([]);
});

it('only counts the 50 newest transactions', function () {
    foreach (range(1, 52) as $i) {
        $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 5, now()->subDays($i), categoryId: $this->category->id);
    }
    // Older than the window and clearly different
    $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 999, now()->subYears(5));

    $profile = app(App\Services\PayeeProfileService::class)->calculate($this->payee);

    expect($profile->sample_size)->toBe(50)
        ->and((float) $profile->amount_max)->toBe(5.0);
});

it('excludes schedules and transactions of other payees', function () {
    $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);
    $schedule = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);
    $schedule->forceFill(['schedule' => true])->save();
    $this->createStandardTransaction($this->user, $this->account->id, $this->createPayeeEntity($this->user)->id, 10, now());

    expect(app(App\Services\PayeeProfileService::class)->calculate($this->payee)->sample_size)->toBe(1);
});

it('reads deposits from the payee on the from side', function () {
    $this->createStandardTransaction($this->user, $this->payee->id, $this->account->id, 70, now(), TransactionType::DEPOSIT, categoryId: $this->category->id);

    $profile = app(App\Services\PayeeProfileService::class)->calculate($this->payee);

    expect($profile->sample_size)->toBe(1)
        ->and($profile->typical_account_ids)->toBe([$this->account->id]);
});

it('measures how many transactions have several items', function () {
    $multi = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);
    $multi->transactionItems()->create(['category_id' => $this->category->id, 'amount' => 5]);
    $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);

    $profile = app(App\Services\PayeeProfileService::class)->calculate($this->payee);

    expect((float) $profile->multi_item_share)->toBe(0.5)
        ->and($profile->single_item_dominant_count)->toBe(1);
});

it('queues a recalculation for the payee of a created transaction', function () {
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now());
    Queue::fake();

    event(new TransactionCreated($transaction));

    Queue::assertPushed(RecalculatePayeeProfile::class, 1);
    Queue::assertPushed(RecalculatePayeeProfile::class, fn ($job) => $job->payeeId === $this->payee->id);
});

it('queues a recalculation for both the old and the new payee of an updated transaction', function () {
    $oldPayee = $this->createPayeeEntity($this->user, ['active' => true]);
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now());
    Queue::fake();

    event(new TransactionUpdated($transaction, ['config' => ['account_to_id' => $oldPayee->id]]));

    Queue::assertPushed(RecalculatePayeeProfile::class, 2);
    Queue::assertPushed(RecalculatePayeeProfile::class, fn ($job) => $job->payeeId === $oldPayee->id);
    Queue::assertPushed(RecalculatePayeeProfile::class, fn ($job) => $job->payeeId === $this->payee->id);
});

it('queues a recalculation for the payee of a deleted transaction and drops it from the profile', function () {
    $keep = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);
    $gone = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);
    $gone->loadDetails();
    $gone->delete();

    event(new TransactionDeleted($gone));

    expect(PayeeProfile::where('account_entity_id', $this->payee->id)->first()->sample_size)->toBe(1)
        ->and($keep->exists)->toBeTrue();
});

it('does not queue anything for a schedule or an account-only transfer', function () {
    $schedule = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now());
    $schedule->forceFill(['schedule' => true])->save();
    $transfer = $this->createStandardTransaction($this->user, $this->account->id, $this->createAccountEntity($this->user)->id, 10, now(), TransactionType::TRANSFER);
    Queue::fake();

    event(new TransactionCreated($schedule));
    event(new TransactionCreated($transfer));

    Queue::assertNotPushed(RecalculatePayeeProfile::class);
});

it('recalculates every payee of a user with the nightly command', function () {
    $other = $this->createPayeeEntity($this->user);
    $foreign = $this->createPayeeEntity(User::factory()->create());
    $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now(), categoryId: $this->category->id);
    PayeeProfile::query()->delete();

    $this->artisan('app:payees:recalculate-profiles', ['userId' => $this->user->id])->assertSuccessful();

    expect(PayeeProfile::where('account_entity_id', $this->payee->id)->value('sample_size'))->toBe(1)
        ->and(PayeeProfile::where('account_entity_id', $other->id)->exists())->toBeTrue()
        ->and(PayeeProfile::where('account_entity_id', $foreign->id)->exists())->toBeFalse();
});

it('recalculates the previous payee when a transaction stops being a standard one', function () {
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, now());
    $transaction->forceFill(['config_type' => 'investment']);
    Queue::fake();

    event(new TransactionUpdated($transaction, [
        'transaction' => ['config_type' => 'standard'],
        'previous_standard_config' => ['account_from_id' => $this->account->id, 'account_to_id' => $this->payee->id],
    ]));

    Queue::assertPushed(RecalculatePayeeProfile::class, fn ($job) => $job->payeeId === $this->payee->id);
});
