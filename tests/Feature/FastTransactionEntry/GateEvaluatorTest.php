<?php

use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\Payee;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\AutoRecord\GateEvaluator;
use App\Services\PayeeProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    AiUserSettings::factory()->enabled()->create(['user_id' => $this->user->id, 'auto_record_enabled' => true]);
    $this->account = $this->createAccountEntity($this->user, ['alias' => "*1234\nVisa"]);
    $this->category = Category::factory()->for($this->user)->create(['active' => true]);
    $this->currency = $this->account->config->currency->iso_code;

    // A payee with $count single-item transactions of 10-12 in one category (median 11)
    $this->payeeWithHistory = function (int $count = 12, array $name = ['name' => 'Parking Kft'], array $config = []) {
        $payee = $this->createPayeeEntity($this->user, $name + ['active' => true]);
        $payee->config->update($config);
        foreach (range(1, $count) as $i) {
            $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 10 + ($i % 3), now()->subDays($i), categoryId: $this->category->id);
        }
        app(PayeeProfileService::class)->calculate($payee);
        $this->payee = $payee;

        return $payee;
    };

    // The draft as document processing leaves it: the payee and the account it matched are already in it
    $this->document = function (array $raw = [], array $draft = [], string $kind = 'bank_notification', ?array $items = null) {
        $payload = array_replace_recursive([
            'schema_version' => 2,
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'date' => now()->subDays(30)->toDateString(),
            'config' => ['amount_from' => '11.00', 'amount_to' => '11.00', 'account_from_id' => $this->account->id, 'account_to_id' => ($this->payee ?? null)?->id],
            'transaction_items' => $items ?? [],
            'raw' => $raw + ['payee' => 'Parking Kft', 'card_last_digits' => '1234', 'currency' => $this->currency],
        ], $draft);

        return AiDocument::factory()->for($this->user)->readyForReview()->withDraft($payload)->create(['document_kind' => $kind]);
    };

    $this->evaluate = fn (AiDocument $document) => app(GateEvaluator::class)->evaluate($document);
});

it('passes every gate for a qualifying payee and resolves what to record', function () {
    $payee = ($this->payeeWithHistory)();

    $result = ($this->evaluate)(($this->document)());

    expect($result->passed)->toBeTrue()
        ->and($result->payee->id)->toBe($payee->id)
        ->and($result->accountId)->toBe($this->account->id)
        ->and($result->items)->toHaveCount(1)
        ->and($result->items[0]['category_id'])->toBe($this->category->id)
        ->and($result->items[0]['amount'])->toBe('11.00')
        ->and($result->reasons)->not->toBeEmpty();
});

it('fails gate 0 when auto-recording or AI is off', function (string $setting) {
    ($this->payeeWithHistory)();
    AiUserSettings::query()->where('user_id', $this->user->id)->update([$setting => false]);

    $result = ($this->evaluate)(($this->document)());

    expect($result->passed)->toBeFalse()->and($result->failedGate)->toBe('switch');
})->with(['auto_record_enabled', 'ai_enabled']);

it('fails gate 1 on missing or unusable extraction', function (array $raw, array $draft) {
    ($this->payeeWithHistory)();

    $result = ($this->evaluate)(($this->document)($raw, $draft));

    expect($result->failedGate)->toBe('extraction');
})->with([
    'future date' => [[], ['date' => '2999-01-01']],
    'zero amount' => [[], ['config' => ['amount_from' => '0']]],
    'no payee text' => [['payee' => ''], []],
    'a transfer' => [[], ['transaction_type' => 'transfer']],
]);

it('fails gate 2 when no payee matches', function () {
    ($this->payeeWithHistory)();

    $result = ($this->evaluate)(($this->document)(['payee' => 'Completely Different Store']));

    expect($result->failedGate)->toBe('payee');
});

it('fails gate 2 when the draft carries no payee', function () {
    ($this->payeeWithHistory)();

    $result = ($this->evaluate)(($this->document)(draft: ['config' => ['account_to_id' => null]]));

    expect($result->failedGate)->toBe('payee');
});

it('fails gate 2 when the draft payee is no certain match of the payee text', function () {
    // The matching suggested this payee, but "Parkinson Clinic" only resembles it: a person decides
    ($this->payeeWithHistory)(name: ['name' => 'Budapest Parking']);

    $result = ($this->evaluate)(($this->document)(['payee' => 'Budapest Parkinson Clinic']));

    expect($result->failedGate)->toBe('payee')
        ->and($result->reason())->toContain('Budapest Parkinson Clinic');
});

it('fails gate 2 when the draft payee differs from the certain match', function () {
    ($this->payeeWithHistory)();
    $other = $this->createPayeeEntity($this->user, ['name' => 'Other Kft', 'active' => true]);

    $result = ($this->evaluate)(($this->document)(draft: ['config' => ['account_to_id' => $other->id]]));

    expect($result->failedGate)->toBe('payee');
});

it('matches the payee by a leading alias', function () {
    $payee = ($this->payeeWithHistory)(name: ['name' => 'OMV', 'alias' => 'OMV Hungary']);

    $result = ($this->evaluate)(($this->document)(['payee' => 'OMV Hungary 4471 Budapest']));

    expect($result->passed)->toBeTrue()->and($result->payee->id)->toBe($payee->id);
});

it('fails gate 3 for a payee set to never, and skips only gate 4 for always', function () {
    ($this->payeeWithHistory)(config: ['auto_record_policy' => Payee::POLICY_NEVER]);
    expect(($this->evaluate)(($this->document)())->failedGate)->toBe('policy');

    // An always-allowed payee with too little history still passes the history gate...
    $young = ($this->payeeWithHistory)(2, ['name' => 'Cafe Nero'], ['auto_record_policy' => Payee::POLICY_ALWAYS]);
    $result = ($this->evaluate)(($this->document)(['payee' => 'Cafe Nero']));
    expect($result->passed)->toBeTrue()->and($result->payee->id)->toBe($young->id);

    // ... but not the account gate
    $result = ($this->evaluate)(($this->document)(['payee' => 'Cafe Nero', 'card_last_digits' => null]));
    expect($result->failedGate)->toBe('account');
});

it('fails gate 4 when the history is too short or too mixed', function () {
    ($this->payeeWithHistory)(5);

    expect(($this->evaluate)(($this->document)())->failedGate)->toBe('history');
});

it('fails gate 5 without a matching card ending, or with an ambiguous one', function () {
    ($this->payeeWithHistory)();

    expect(($this->evaluate)(($this->document)(['card_last_digits' => '9999']))->failedGate)->toBe('account');

    $this->createAccountEntity($this->user, ['alias' => '1234']);
    expect(($this->evaluate)(($this->document)())->failedGate)->toBe('account');
});

it('matches the card ending to the closing digits of any alias line', function (string $alias, array $raw) {
    ($this->payeeWithHistory)();
    $account = $this->createAccountEntity($this->user, ['alias' => $alias]);

    $result = ($this->evaluate)(($this->document)($raw, ['config' => ['account_from_id' => $account->id]]));

    expect($result->passed)->toBeTrue()->and($result->accountId)->toBe($account->id);
})->with([
    'bare digits' => ['5678', ['card_last_digits' => '5678']],
    'masked' => ['****5678', ['card_last_digits' => '5678']],
    'with a label' => ['Visa Debit 5678', ['card_last_digits' => '5678']],
    'a whole masked number' => ['400322******5678', ['card_last_digits' => '5678']],
    'a masked number as the account text' => ['Visa Debit 5678', ['card_last_digits' => null, 'account' => '400322******5678']],
]);

it('does not accept a draft account that no certain match backs, or one that is inactive', function () {
    ($this->payeeWithHistory)();
    $other = $this->createAccountEntity($this->user, ['alias' => '9999']);

    // The draft names an account, but the card ending belongs to another one
    expect(($this->evaluate)(($this->document)(draft: ['config' => ['account_from_id' => $other->id]]))->failedGate)->toBe('account');

    $this->account->update(['active' => false]);
    expect(($this->evaluate)(($this->document)())->failedGate)->toBe('account');
});

it('takes the account and category from the single template when no card matches', function () {
    $payee = ($this->payeeWithHistory)();
    $otherAccount = $this->createAccountEntity($this->user);
    $otherCategory = Category::factory()->for($this->user)->create(['active' => true]);

    $template = new TransactionTemplate([
        'name' => 'Parking',
        'draft' => [
            'schema_version' => 2,
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'config' => ['account_from_id' => $otherAccount->id, 'account_to_id' => $payee->id],
            'transaction_items' => [['category_id' => $otherCategory->id]],
        ],
        'is_featured' => false,
    ]);
    $template->user_id = $this->user->id;
    $template->payee_id = $payee->id;
    $template->save();

    $result = ($this->evaluate)(($this->document)(['card_last_digits' => null], ['config' => ['account_from_id' => null]]));

    expect($result->passed)->toBeTrue()
        ->and($result->accountId)->toBe($otherAccount->id)
        ->and($result->items[0]['category_id'])->toBe($otherCategory->id);
});

it('holds a summary for a payee that expects itemization, and passes the itemized receipt', function () {
    ($this->payeeWithHistory)(config: ['itemization_expected' => true]);

    $notification = ($this->document)();
    $held = ($this->evaluate)($notification);
    expect($held->passed)->toBeFalse()->and($held->hold)->toBeTrue()->and($held->failedGate)->toBe('completeness');

    // The receipt takes the notification over and closes it first, so the duplicates gate does not see it
    $notification->forceFill(['status' => 'duplicate'])->save();

    $items = [['amount' => '11.00', 'description' => 'Parking', 'recommended_category_id' => $this->category->id, 'match_type' => 'exact']];
    $receipt = ($this->evaluate)(($this->document)(kind: 'receipt', items: $items));
    expect($receipt->passed)->toBeTrue()->and($receipt->items[0]['category_id'])->toBe($this->category->id);
});

it('fails an itemized document with uncategorized items or items that do not add up', function (array $items) {
    ($this->payeeWithHistory)();

    $result = ($this->evaluate)(($this->document)(kind: 'receipt', items: $items));

    expect($result->failedGate)->toBe('extraction');
})->with([
    'uncategorized' => [[['amount' => '11.00', 'description' => 'x', 'recommended_category_id' => null, 'match_type' => null]]],
    'not exact' => [[['amount' => '11.00', 'description' => 'x', 'recommended_category_id' => 1, 'match_type' => 'ai']]],
    'wrong sum' => [[['amount' => '5.00', 'description' => 'x', 'recommended_category_id' => 1, 'match_type' => 'exact']]],
]);

it('fails gate 7 for an amount far from the median, and passes a known amount outside the tolerance', function () {
    $payee = ($this->payeeWithHistory)();
    $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 40, now()->subDays(20), categoryId: $this->category->id);
    app(PayeeProfileService::class)->calculate($payee);

    $far = ($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => '110.00']]));
    expect($far->failedGate)->toBe('amount');

    $known = ($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => '40.00']]));
    expect($known->passed)->toBeTrue();
});

it('judges the amount against the recent history', function () {
    $payee = $this->createPayeeEntity($this->user, ['name' => 'Parking Kft', 'active' => true]);
    foreach (range(1, 40) as $i) {
        $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 1400 + $i, now()->subYears(3)->subDays($i), categoryId: $this->category->id);
    }
    foreach (range(1, 10) as $i) {
        $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 2250 + $i, now()->subDays($i), categoryId: $this->category->id);
    }
    app(PayeeProfileService::class)->calculate($payee);
    $this->payee = $payee;

    expect(($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => '2300.00']]))->passed)->toBeTrue();

    $far = ($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => '6000.00']]));
    expect($far->failedGate)->toBe('amount')
        ->and($far->context)->toHaveKeys(['amount', 'median', 'from', 'to']);
});

it('widens the accepted band for a wide-spread payee but not for identical amounts', function () {
    $wide = $this->createPayeeEntity($this->user, ['name' => 'Parking Kft', 'active' => true]);
    foreach ([60, 80, 100, 120, 140, 60, 80, 100, 120, 140, 100, 100] as $i => $amount) {
        $this->createStandardTransaction($this->user, $this->account->id, $wide->id, $amount, now()->subDays($i + 1), categoryId: $this->category->id);
    }
    app(PayeeProfileService::class)->calculate($wide);
    $this->payee = $wide;

    // 135 is 35% off the median 100: outside 20%, inside the spread band
    expect(($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => '135.00']]))->passed)->toBeTrue();

    $tight = $this->createPayeeEntity($this->user, ['name' => 'Taxi Kft', 'active' => true]);
    foreach (range(1, 12) as $i) {
        $this->createStandardTransaction($this->user, $this->account->id, $tight->id, 100, now()->subDays($i), categoryId: $this->category->id);
    }
    app(PayeeProfileService::class)->calculate($tight);
    $this->payee = $tight;

    $result = ($this->evaluate)(($this->document)(['payee' => 'Taxi Kft'], ['config' => ['amount_from' => '121.00']]));
    expect($result->failedGate)->toBe('amount');
});

it('fails gate 7 when the document is in another currency than the account', function () {
    ($this->payeeWithHistory)();

    $result = ($this->evaluate)(($this->document)(['currency' => $this->currency === 'EUR' ? 'USD' : 'EUR']));

    expect($result->failedGate)->toBe('amount');
});

it('fails gate 8 when the purchase is already recorded', function () {
    $payee = ($this->payeeWithHistory)();
    $date = now()->subDays(30)->toDateString();
    $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 11, $date, categoryId: $this->category->id);

    $result = ($this->evaluate)(($this->document)());

    expect($result->failedGate)->toBe('duplicates');
});

it('does not accept an amount of another category of the payee', function () {
    $transit = Category::factory()->for($this->user)->create(['active' => true]);
    $payee = $this->createPayeeEntity($this->user, ['name' => 'Parking Kft', 'active' => true]);
    foreach (range(1, 34) as $i) {
        $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 500, now()->subDays($i + 40), categoryId: $this->category->id);
    }
    foreach ([1000, 9450, 10395] as $i => $amount) {
        $this->createStandardTransaction($this->user, $this->account->id, $payee->id, $amount, now()->subDays($i + 80), categoryId: $transit->id);
    }
    app(PayeeProfileService::class)->calculate($payee);
    $this->payee = $payee;

    expect(($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => '500.00']]))->passed)->toBeTrue();

    foreach (['1200.00', '9450.00'] as $amount) {
        expect(($this->evaluate)(($this->document)(draft: ['config' => ['amount_from' => $amount]]))->failedGate)->toBe('amount');
    }
});
