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

        return $payee;
    };

    $this->document = function (array $raw = [], array $draft = [], string $kind = 'bank_notification', ?array $items = null) {
        $payload = array_replace_recursive([
            'schema_version' => 2,
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'date' => now()->subDays(30)->toDateString(),
            'config' => ['amount_from' => '11.00', 'amount_to' => '11.00', 'account_from_id' => null, 'account_to_id' => null],
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

    $result = ($this->evaluate)(($this->document)(['card_last_digits' => null]));

    expect($result->passed)->toBeTrue()
        ->and($result->accountId)->toBe($otherAccount->id)
        ->and($result->items[0]['category_id'])->toBe($otherCategory->id);
});

it('holds a summary for a payee that expects itemization, and passes the itemized receipt', function () {
    ($this->payeeWithHistory)(config: ['itemization_expected' => true]);

    $held = ($this->evaluate)(($this->document)());
    expect($held->passed)->toBeFalse()->and($held->hold)->toBeTrue()->and($held->failedGate)->toBe('completeness');

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
