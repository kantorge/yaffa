<?php

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user, ['*']);
});

function budgetPayload(Category $category, array $overrides = []): array
{
    return [
        'category_id' => $category->id,
        'transaction_type' => 'withdrawal',
        'amount' => 100,
        'frequency' => 'MONTHLY',
        'interval' => 1,
        'start_date' => Carbon::now()->subDay()->toDateString(),
        ...$overrides,
    ];
}

// amount (MoneyCast) resolves its currency via the user's base currency when the budget is account-agnostic.
function createBudgetBaseCurrency(User $user): void
{
    Currency::factory()->for($user)->create(['base' => true]);
}

function createBudgetOwnedAccount(User $user): AccountEntity
{
    AccountGroup::factory()->for($user)->create();
    Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

    $account = Account::factory()->withUser($user)->create();

    return AccountEntity::factory()->for($user)->for($account, 'config')->create();
}

it('rejects guests', function () {
    auth()->forgetGuards(); // undo beforeEach's actingAs

    $this->getJson(route('api.v1.budgets.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.budgets.store'), [])->assertUnauthorized();
});

// ===== CREATE =====

it('creates an account-agnostic budget', function () {
    createBudgetBaseCurrency($this->user);

    $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, [
        'account_id' => null,
        'amount' => 250.50,
    ]))
        ->assertCreated()
        ->assertJsonPath('category_id', $this->category->id)
        ->assertJsonPath('account_id', null)
        ->assertJsonPath('active', true);

    $this->assertDatabaseHas('budgets', [
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'account_id' => null,
    ]);
});

it('creates an account-scoped budget', function () {
    $accountEntity = createBudgetOwnedAccount($this->user);

    $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, [
        'account_id' => $accountEntity->id,
        'frequency' => 'WEEKLY',
    ]))
        ->assertCreated()
        ->assertJsonPath('account_id', $accountEntity->id);

    $this->assertDatabaseHas('budgets', [
        'user_id' => $this->user->id,
        'account_id' => $accountEntity->id,
    ]);
});

it('creates a budget with a valid pattern', function (array $overrides, array $expectedJson) {
    createBudgetBaseCurrency($this->user);

    $response = $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, $overrides))
        ->assertCreated();

    foreach ($expectedJson as $path => $value) {
        $response->assertJsonPath($path, $value);
    }
})->with([
    'deposit' => [['transaction_type' => 'deposit'], ['transaction_type' => 'deposit']],
    'days before month end' => [
        ['days_before_month_end' => 3],
        ['days_before_month_end' => 3, 'by_day' => null, 'last_business_day_of_month' => false],
    ],
    'days before month end: last day of the month (lower bound)' => [['days_before_month_end' => 0], ['days_before_month_end' => 0]],
    'days before month end: upper bound' => [['days_before_month_end' => 27], ['days_before_month_end' => 27]],
    'yearly days before month end with by_month' => [
        ['frequency' => 'YEARLY', 'days_before_month_end' => 3, 'by_month' => 6],
        ['days_before_month_end' => 3, 'by_month' => 6],
    ],
    'last business day of month' => [
        ['last_business_day_of_month' => true],
        ['last_business_day_of_month' => true, 'by_day' => null, 'days_before_month_end' => null],
    ],
]);

it('rejects an invalid budget', function (array $overrides, array $errors) {
    $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'zero amount' => [['amount' => 0], ['amount']],
    // Same DECIMAL(12,4) bound TransactionRequest enforces for transaction amounts.
    'amount exceeding DECIMAL(12,4)' => [['amount' => 100000000], ['amount']],
    'transfer transaction type' => [['transaction_type' => 'transfer'], ['transaction_type']],
    'both end_date and count' => [['end_date' => Carbon::now()->addYear()->toDateString(), 'count' => 5], ['end_date', 'count']],
    // Regression guard for the DoS finding fixed in ValidatesRecurrenceRule::maxRecurrencePeriodsRule():
    // a start_date spanning thousands of periods made every later RecurrenceRuleService call on the
    // budget slow (~4s/call for a centuries-old start_date). 10 years of DAILY is ~3650 periods,
    // comfortably over the 2000-period cap.
    'start_date spanning too many periods' => [
        ['frequency' => 'DAILY', 'start_date' => Carbon::now()->subYears(10)->toDateString()],
        ['start_date'],
    ],
    'days before month end with by_day' => [['by_day' => '1WE', 'days_before_month_end' => 3], ['days_before_month_end']],
    'days before month end with weekly frequency' => [['frequency' => 'WEEKLY', 'days_before_month_end' => 3], ['days_before_month_end']],
    'yearly days before month end without by_month' => [['frequency' => 'YEARLY', 'days_before_month_end' => 3], ['by_month']],
    'days before month end above 27' => [['days_before_month_end' => 28], ['days_before_month_end']],
    'negative days before month end' => [['days_before_month_end' => -1], ['days_before_month_end']],
    'last business day with by_day' => [['by_day' => '1WE', 'last_business_day_of_month' => true], ['last_business_day_of_month']],
    'last business day with weekly frequency' => [['frequency' => 'WEEKLY', 'last_business_day_of_month' => true], ['last_business_day_of_month']],
    'yearly last business day without by_month' => [['frequency' => 'YEARLY', 'last_business_day_of_month' => true], ['by_month']],
    'last business day with days before month end' => [
        ['days_before_month_end' => 3, 'last_business_day_of_month' => true],
        ['days_before_month_end', 'last_business_day_of_month'],
    ],
]);

it('rejects another user\'s category', function () {
    $otherCategory = Category::factory()->for(User::factory()->create())->create();

    $this->postJson(route('api.v1.budgets.store'), budgetPayload($otherCategory))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

it('rejects a payee as the account', function () {
    $payee = AccountEntity::factory()->asPayee($this->user)->create();

    $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, ['account_id' => $payee->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id']);
});

it('ignores a client-sent active flag', function () {
    createBudgetBaseCurrency($this->user);

    // A rule that has already run out of occurrences: the client-sent `active: true` must be ignored.
    $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, [
        'frequency' => 'DAILY',
        'start_date' => Carbon::now()->subDays(10)->toDateString(),
        'end_date' => Carbon::now()->subDay()->toDateString(),
        'active' => true,
    ]))
        ->assertCreated()
        ->assertJsonPath('active', false);
});

// ===== READ / UPDATE / DELETE =====

it('shows a single budget', function () {
    $budget = Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);

    $this->getJson(route('api.v1.budgets.show', ['budget' => $budget->id]))
        ->assertOk()
        ->assertJsonPath('id', $budget->id);
});

it('updates a budget', function () {
    $budget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'amount' => 100,
    ]);

    $this->patchJson(route('api.v1.budgets.update', ['budget' => $budget->id]), [
        'category_id' => $this->category->id,
        'transaction_type' => $budget->transaction_type->value,
        'amount' => 200,
        'frequency' => $budget->frequency,
        'interval' => $budget->interval,
        'start_date' => $budget->start_date->toDateString(),
    ])
        ->assertOk()
        ->assertJsonPath('amount', '200.0000');

    $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'amount' => 200]);
});

it('deletes a budget', function () {
    $budget = Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);

    $this->deleteJson(route('api.v1.budgets.destroy', ['budget' => $budget->id]))->assertOk();

    $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
});

it('forbids managing another user\'s budget', function () {
    $owner = User::factory()->create();
    $budget = Budget::factory()->create([
        'user_id' => $owner->id,
        'category_id' => Category::factory()->for($owner)->create()->id,
    ]);

    $this->getJson(route('api.v1.budgets.show', ['budget' => $budget->id]))->assertForbidden();

    $this->patchJson(
        route('api.v1.budgets.update', ['budget' => $budget->id]),
        budgetPayload($this->category, ['amount' => 50, 'start_date' => Carbon::now()->toDateString()])
    )->assertForbidden();

    $this->deleteJson(route('api.v1.budgets.destroy', ['budget' => $budget->id]))->assertForbidden();
});

it('lists only the authenticated user\'s budgets', function () {
    Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);

    $otherUser = User::factory()->create();
    Budget::factory()->create([
        'user_id' => $otherUser->id,
        'category_id' => Category::factory()->for($otherUser)->create()->id,
    ]);

    $this->getJson(route('api.v1.budgets.index'))
        ->assertOk()
        ->assertJsonCount(1);
});

// ===== REPLACE =====

/*
 * The 'replace' action (mirroring TransactionApiController's schedule replace flow) must not
 * rewrite the source budget's own history: it closes the source row's end_date and creates a
 * brand new row for the new pattern/amount, rather than mutating the source in place.
 */
it('closes the source budget and creates a new one on replace', function () {
    createBudgetBaseCurrency($this->user);

    $sourceStartDate = Carbon::now()->subMonths(2)->startOfDay();
    $source = Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'amount' => 100,
        'frequency' => 'MONTHLY',
        'interval' => 1,
        'start_date' => $sourceStartDate,
        'end_date' => null,
        'count' => null,
    ]);

    $newStartDate = Carbon::now()->startOfDay();
    $originalEndDate = $newStartDate->copy()->subDay();

    $response = $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, [
        'action' => 'replace',
        'id' => $source->id,
        'transaction_type' => $source->transaction_type->value,
        'amount' => 200,
        'start_date' => $newStartDate->toDateString(),
        'original_schedule_config' => [
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $sourceStartDate->toDateString(),
            'end_date' => $originalEndDate->toDateString(),
        ],
    ]))
        ->assertCreated()
        ->assertJsonPath('amount', '200.0000');

    expect(Carbon::parse($response->json('start_date'))->toDateString())->toBe($newStartDate->toDateString());

    $newBudgetId = $response->json('id');
    expect($newBudgetId)->not->toBe($source->id);

    // The source keeps its own amount/pattern - only end_date is closed. end_date is a
    // virtual attribute (decomposed from `rrule`), not a real column, so it's checked via
    // the model rather than assertDatabaseHas().
    $this->assertDatabaseHas('budgets', ['id' => $source->id, 'amount' => 100]);
    expect(Budget::find($source->id)->end_date->toDateString())->toBe($originalEndDate->toDateString());
    $this->assertDatabaseHas('budgets', [
        'id' => $newBudgetId,
        'amount' => 200,
        'start_date' => $newStartDate->toDateString(),
    ]);
});

it('requires the replaced budget to be owned by the user', function () {
    $owner = User::factory()->create();
    $source = Budget::factory()->create([
        'user_id' => $owner->id,
        'category_id' => Category::factory()->for($owner)->create()->id,
    ]);

    $this->postJson(route('api.v1.budgets.store'), budgetPayload($this->category, [
        'action' => 'replace',
        'id' => $source->id,
        'amount' => 200,
        'start_date' => Carbon::now()->toDateString(),
        'original_schedule_config' => [
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subMonth()->toDateString(),
            'end_date' => Carbon::now()->subDay()->toDateString(),
        ],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id']);
});
