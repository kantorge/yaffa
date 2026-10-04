<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TransactionDraftService();
    $this->user = User::factory()->create();
    // An unsaved transaction resolves its currency through the authenticated user, as on the draft form
    $this->actingAs($this->user);
    $this->account = AccountEntity::factory()->asAccount($this->user)->create(['active' => true]);
    $this->payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => true]);
    $this->category = Category::factory()->for($this->user)->create(['active' => true]);

    $this->standardDraft = fn (array $overrides = []): array => array_replace_recursive([
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'date' => '2026-10-01',
        'config' => [
            'amount_from' => '1500.00',
            'amount_to' => '1500.00',
            'account_from_id' => $this->account->id,
            'account_to_id' => $this->payee->id,
        ],
        'transaction_items' => [
            ['amount' => '1500.00', 'category_id' => $this->category->id],
        ],
    ], $overrides);
});

it('upgrades a v1 draft to v2 without renaming anything', function () {
    $v1 = [
        'raw' => ['payee' => 'Parking'],
        'date' => '2026-10-01',
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'config' => [
            'amount_from' => 12.5,
            'amount_to' => 12.5,
            'account_from_id' => $this->account->id,
            'account_to_id' => $this->payee->id,
        ],
        'transaction_items' => [[
            'amount' => 12.5,
            'description' => 'Parking',
            'recommended_category_id' => $this->category->id,
            'match_type' => 'exact',
            'confidence_score' => 0.97,
        ]],
    ];

    ['draft' => $draft, 'notices' => $notices] = $this->service->normalize($v1, $this->user);

    expect($draft['schema_version'])->toBe(2)
        ->and($notices)->toBe([])
        ->and(array_keys($draft))->toBe(['schema_version', ...array_keys($v1)])
        ->and($draft['config']['amount_from'])->toBe('12.5')
        ->and($draft['transaction_items'][0]['amount'])->toBe('12.5')
        ->and($draft['transaction_items'][0]['description'])->toBe('Parking');
});

it('rejects unknown keys', function (array $overrides, string $errorKey) {
    expect(fn () => $this->service->normalize(($this->standardDraft)($overrides), $this->user))
        ->toThrow(function (ValidationException $e) use ($errorKey) {
            expect($e->errors())->toHaveKey($errorKey);
        });
})->with([
    'top level' => [['payee_id' => 1], 'draft'],
    'config' => [['config' => ['investment_id' => 1]], 'draft.config'],
    'item' => [['transaction_items' => [['amount' => '1', 'foo' => 'bar']]], 'draft.transaction_items.0'],
]);

it('rejects a draft without a config type or with an unsupported schema version', function (array $draft) {
    expect(fn () => $this->service->normalize($draft, $this->user))->toThrow(ValidationException::class);
})->with([
    'no config type' => [['transaction_type' => 'withdrawal']],
    'future version' => [['schema_version' => 3, 'config_type' => 'standard']],
    'type of other config' => [['config_type' => 'standard', 'transaction_type' => 'buy']],
]);

it('keeps amounts as decimal strings', function () {
    ['draft' => $draft] = $this->service->normalize(($this->standardDraft)(), $this->user);

    expect($draft['config']['amount_from'])->toBe('1500.00')
        ->and($draft['config']['amount_to'])->toBe('1500.00')
        ->and($draft['transaction_items'][0]['amount'])->toBe('1500.00');
});

it('blanks a stale account, payee or category reference with a notice', function (string $field, Closure $makeStale, string $reason) {
    $makeStale->call($this);

    ['draft' => $draft, 'notices' => $notices] = $this->service->normalize(($this->standardDraft)(), $this->user);

    expect(data_get($draft, $field))->toBeNull()
        ->and(Illuminate\Support\Arr::has($draft, $field))->toBeFalse()
        ->and($notices)->toBe([['field' => $field, 'reason' => $reason]]);
})->with([
    'deleted account' => ['config.account_from_id', function () {
        $this->account->delete();
    }, 'not_found'],
    'inactive account' => ['config.account_from_id', function () {
        $this->account->update(['active' => false]);
    }, 'inactive'],
    'foreign payee' => ['config.account_to_id', function () {
        $this->payee->update(['user_id' => User::factory()->create()->id]);
    }, 'not_found'],
    'inactive payee' => ['config.account_to_id', function () {
        $this->payee->update(['active' => false]);
    }, 'inactive'],
    'deleted category' => ['transaction_items.0.category_id', function () {
        $this->category->delete();
    }, 'not_found'],
    'foreign category' => ['transaction_items.0.category_id', function () {
        $this->category->forceFill(['user_id' => User::factory()->create()->id])->save();
    }, 'not_found'],
]);

it('drops only the stale tags of an item', function () {
    $tag = Tag::factory()->for($this->user)->create(['active' => true]);
    $inactiveTag = Tag::factory()->for($this->user)->create(['active' => false]);

    ['draft' => $draft, 'notices' => $notices] = $this->service->normalize(($this->standardDraft)([
        'transaction_items' => [['tag_ids' => [$inactiveTag->id, $tag->id]]],
    ]), $this->user);

    expect($draft['transaction_items'][0]['tag_ids'])->toBe([$tag->id])
        ->and($notices)->toBe([['field' => 'transaction_items.0.tag_ids.0', 'reason' => 'inactive']]);
});

it('round-trips a standard transaction without the date', function () {
    // The withdrawal factory attaches up to 3 random tags of the user
    [$tag] = Tag::factory()->for($this->user)->count(3)->create(['active' => true]);
    $transaction = Transaction::factory()->withdrawal($this->user)->create(['comment' => 'Lunch']);
    $transaction->transactionItems()->first()->tags()->sync([$tag->id]);
    // The factory picks random categories, which may be inactive and would be blanked
    Category::query()->where('user_id', $this->user->id)->update(['active' => true]);
    $transaction->load('config', 'transactionItems.tags');

    $draft = $this->service->fromTransaction($transaction);

    expect($draft)->not->toHaveKey('date')
        ->and($draft['schema_version'])->toBe(2)
        ->and($draft['transaction_type'])->toBe('withdrawal')
        ->and($draft['comment'])->toBe('Lunch')
        ->and($draft['config']['amount_from'])->toBe((string) $transaction->config->amount_from->getAmount())
        ->and($draft['config']['account_to_id'])->toBe($transaction->config->account_to_id)
        ->and($draft['transaction_items'])->toHaveCount($transaction->transactionItems->count())
        ->and($draft['transaction_items'][0]['tag_ids'])->toBe([$tag->id]);

    // The draft is valid as is
    expect($this->service->normalize($draft, $this->user))->toBe(['draft' => $draft, 'notices' => []]);

    $unsaved = $this->service->toUnsavedTransaction($draft, $this->user);

    expect($unsaved->exists)->toBeFalse()
        ->and($unsaved->date)->toBeNull()
        ->and($unsaved->comment)->toBe('Lunch')
        ->and($unsaved->config->account_from_id)->toBe($transaction->config->account_from_id)
        ->and($unsaved->config->account_to_id)->toBe($transaction->config->account_to_id)
        ->and($unsaved->config->amount_from->isEqualTo($transaction->config->amount_from))->toBeTrue()
        ->and($unsaved->transactionItems->map(fn ($item) => [$item->category_id, (string) $item->amount->getAmount()])->all())
        ->toBe($transaction->transactionItems->map(fn ($item) => [$item->category_id, (string) $item->amount->getAmount()])->all());
});

it('round-trips an investment transaction without the date', function () {
    $currency = $this->account->config->currency;
    $investment = Investment::factory()->create([
        'user_id' => $this->user->id,
        'active' => true,
        'currency_id' => $currency->id,
        'investment_group_id' => InvestmentGroup::factory()->for($this->user)->create()->id,
    ]);
    $transaction = Transaction::factory()->buy($this->user, [
        'account_id' => $this->account->id,
        'investment_id' => $investment->id,
    ])->create();
    $transaction->load('config');

    $draft = $this->service->fromTransaction($transaction);

    expect($draft)->not->toHaveKey('date')
        ->and($draft)->not->toHaveKey('transaction_items')
        ->and($draft['config_type'])->toBe('investment')
        ->and($draft['transaction_type'])->toBe('buy')
        ->and($draft['config']['account_id'])->toBe($this->account->id)
        ->and($draft['config']['investment_id'])->toBe($investment->id)
        ->and($draft['config']['quantity'])->toBe((string) $transaction->config->quantity)
        ->and($draft['config']['price'])->toBe((string) $transaction->config->price->getAmount())
        ->and($draft['config']['dividend'])->toBeNull();

    expect($this->service->normalize($draft, $this->user))->toBe(['draft' => $draft, 'notices' => []]);

    $unsaved = $this->service->toUnsavedTransaction($draft, $this->user);

    expect($unsaved->date)->toBeNull()
        ->and($unsaved->config->investment_id)->toBe($investment->id)
        ->and($unsaved->config->quantity->isEqualTo($transaction->config->quantity))->toBeTrue();
});

it('enriches the matched account and investment of an interest yield draft', function () {
    $investment = Investment::factory()->create([
        'user_id' => $this->user->id,
        'active' => true,
        'currency_id' => $this->account->config->currency_id,
        'investment_group_id' => InvestmentGroup::factory()->for($this->user)->create()->id,
    ]);

    $enriched = $this->service->enrich([
        'config_type' => 'investment',
        'transaction_type' => 'interest_yield',
        'config' => ['account_id' => $this->account->id, 'investment_id' => $investment->id],
    ], $this->user);

    expect($enriched['matched_entities']['account']['id'])->toBe($this->account->id)
        ->and($enriched['matched_entities']['investment']['id'])->toBe($investment->id);
});
