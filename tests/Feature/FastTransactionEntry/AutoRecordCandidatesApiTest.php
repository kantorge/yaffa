<?php

use App\Models\Category;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\PayeeProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = $this->createAccountEntity($this->user);
    $this->category = Category::factory()->for($this->user)->create(['active' => true]);

    // A payee with $count single-item transactions in one category
    $this->payeeWithHistory = function (int $count, array $payeeAttributes = [], array $configAttributes = [], ?User $owner = null) {
        $owner ??= $this->user;
        $payee = $this->createPayeeEntity($owner, array_merge(['active' => true], $payeeAttributes));
        $payee->config->update($configAttributes);
        foreach (range(1, $count) as $i) {
            $this->createStandardTransaction($owner, $this->account->id, $payee->id, 10 + ($i % 3), now()->subDays($i), categoryId: $this->category->id);
        }
        app(PayeeProfileService::class)->calculate($payee);

        return $payee;
    };

    $this->groups = fn () => $this->actingAs($this->user)
        ->getJson(route('api.v1.payees.auto-record-candidates'))
        ->assertOk()
        ->json();
    $this->ids = fn (array $rows) => collect($rows)->pluck('payee_id')->all();
});

it('lists a payee with 11 consistent transactions as qualifying with the numbers behind it', function () {
    $payee = ($this->payeeWithHistory)(11);

    $groups = ($this->groups)();
    $row = collect($groups['qualifying'])->firstWhere('payee_id', $payee->id);

    expect($row)->not->toBeNull()
        ->and($row['sample_size'])->toBe(11)
        ->and($row['dominant_category_id'])->toBe($this->category->id)
        ->and((float) $row['wilson_lower'])->toBeGreaterThanOrEqual(0.8)
        ->and($row['reason'])->not->toBe('')
        ->and(($this->ids)($groups['near']))->not->toContain($payee->id);
});

it('lists a payee with 10 consistent transactions as near, missing one', function () {
    $payee = ($this->payeeWithHistory)(10);

    $groups = ($this->groups)();
    $row = collect($groups['near'])->firstWhere('payee_id', $payee->id);

    expect($row)->not->toBeNull()
        ->and($row['missing_transactions'])->toBe(1)
        ->and(($this->ids)($groups['qualifying']))->not->toContain($payee->id);
});

it('lists a young but perfectly consistent payee as near with the transactions it still needs', function () {
    $payee = ($this->payeeWithHistory)(3);

    $row = collect(($this->groups)()['near'])->firstWhere('payee_id', $payee->id);

    expect($row['missing_transactions'])->toBe(8);
});

it('keeps a payee set to never out of qualifying and near', function () {
    $payee = ($this->payeeWithHistory)(11, [], ['auto_record_policy' => 'never']);

    $groups = ($this->groups)();

    expect(($this->ids)($groups['qualifying']))->not->toContain($payee->id)
        ->and(($this->ids)($groups['near']))->not->toContain($payee->id);
});

it('qualifies a payee set to always regardless of history', function () {
    $payee = ($this->payeeWithHistory)(1, [], ['auto_record_policy' => 'always']);

    expect(($this->ids)(($this->groups)()['qualifying']))->toContain($payee->id);
});

it('flags a grocery-like payee as an itemization mismatch', function () {
    $payee = $this->createPayeeEntity($this->user, ['active' => true]);
    foreach (range(1, 6) as $i) {
        $transaction = $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 30, now()->subDays($i), categoryId: $this->category->id);
        $transaction->transactionItems()->create(['category_id' => Category::factory()->for($this->user)->create()->id, 'amount' => 10]);
    }
    app(PayeeProfileService::class)->calculate($payee);

    expect(($this->ids)(($this->groups)()['itemization_mismatch']))->toContain($payee->id);

    // The same history is fine once the payee is expected to be itemized
    $payee->config->update(['itemization_expected' => true]);
    expect(($this->ids)(($this->groups)()['itemization_mismatch']))->not->toContain($payee->id);
});

it('flags a single-item payee that is expected to be itemized', function () {
    $payee = ($this->payeeWithHistory)(6, [], ['itemization_expected' => true]);

    expect(($this->ids)(($this->groups)()['itemization_mismatch']))->toContain($payee->id);
});

it('suggests a template for a repeating single-category payee that has none', function () {
    $payee = $this->createPayeeEntity($this->user, ['active' => true]);
    foreach (range(1, 6) as $i) {
        $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 12, now()->subDays($i), categoryId: $this->category->id);
    }
    app(PayeeProfileService::class)->calculate($payee);

    expect(($this->ids)(($this->groups)()['template_candidates']))->toContain($payee->id);

    TransactionTemplate::factory()->for($this->user)->create(['payee_id' => $payee->id]);
    expect(($this->ids)(($this->groups)()['template_candidates']))->not->toContain($payee->id);
});

it('does not expose payees of other users', function () {
    $foreignUser = User::factory()->create();
    $foreign = ($this->payeeWithHistory)(11, [], [], $foreignUser);

    $groups = ($this->groups)();

    expect(collect($groups)->flatten(1)->pluck('payee_id')->all())->not->toContain($foreign->id);
});

it('respects the thresholds of the user', function () {
    $payee = ($this->payeeWithHistory)(10);
    $this->user->aiUserSettings()->create(['auto_record_min_history' => 5, 'auto_record_wilson_min' => 0.7]);

    expect(($this->ids)(($this->groups)()['qualifying']))->toContain($payee->id);
});

it('reports the active flag and whether the payee has an active schedule', function () {
    $scheduled = ($this->payeeWithHistory)(11);
    $plain = ($this->payeeWithHistory)(11, ['active' => false]);
    $schedule = $this->createStandardTransaction($this->user, $this->account->id, $scheduled->id, 10, now());
    $schedule->forceFill(['schedule' => true])->save();
    App\Models\TransactionSchedule::factory()->create(['transaction_id' => $schedule->id, 'active' => true]);
    Illuminate\Support\Facades\DB::table('transaction_schedules')->where('transaction_id', $schedule->id)->update(['active' => true]);

    $rows = collect(($this->groups)()['qualifying'])->keyBy('payee_id');

    expect($rows[$scheduled->id]['has_active_schedule'])->toBeTrue()
        ->and($rows[$scheduled->id]['active'])->toBeTrue()
        ->and($rows[$plain->id]['has_active_schedule'])->toBeFalse()
        ->and($rows[$plain->id]['active'])->toBeFalse();
});
