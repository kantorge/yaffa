<?php

use App\Enums\TransactionType;
use App\Models\AccountEntity;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AssetOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Create a standard transaction with deterministic amounts. Withdrawals go to $payee,
 * deposits come from it. $items is a list of [category_id, amount].
 */
function makeStandardTransaction(
    User $user,
    AccountEntity $payee,
    TransactionType $type,
    string $date,
    array $items,
    ?Currency $currency = null,
    bool $schedule = false,
): Transaction {
    $factory = Transaction::factory();
    $transaction = ($type === TransactionType::WITHDRAWAL ? $factory->withdrawal($user) : $factory->deposit($user))
        ->create(['date' => $date]);

    $total = array_sum(array_column($items, 1));
    $config = $transaction->config;
    $column = $type === TransactionType::WITHDRAWAL ? 'account_to_id' : 'account_from_id';
    $config->update([$column => $payee->id, 'amount_from' => $total, 'amount_to' => $total]);

    DB::table('transaction_items')->where('transaction_id', $transaction->id)->delete();
    foreach ($items as [$categoryId, $amount]) {
        DB::table('transaction_items')->insert([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryId,
            'amount' => $amount,
        ]);
    }

    $update = ['date' => $date, 'schedule' => $schedule];
    if ($currency) {
        $update['currency_id'] = $currency->id;
    }
    DB::table('transactions')->where('id', $transaction->id)->update($update);

    return $transaction;
}

function userWithBaseCurrency(): array
{
    $user = User::factory()->create();
    $base = Currency::factory()->for($user)->create(['base' => true]);

    return [$user, $base];
}

it('shows the payee page to its owner', function () {
    $user = User::factory()->create();
    $payee = AccountEntity::factory()->asPayee($user)->create();

    $this->actingAs($user)
        ->get(route('account-entity.show', $payee))
        ->assertOk()
        ->assertViewIs('payees.show');
});

it('forbids showing another user\'s payee', function () {
    $payee = AccountEntity::factory()->asPayee(User::factory()->create())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('account-entity.show', $payee))
        ->assertForbidden();
});

it('still renders the account page for accounts', function () {
    $user = User::factory()->create();
    $account = AccountEntity::factory()->asAccount($user)->create();

    $this->actingAs($user)
        ->get(route('account-entity.show', $account))
        ->assertOk()
        ->assertViewIs('accounts.show');
});

it('renders a payee without transactions with empty-state values', function () {
    [$user] = userWithBaseCurrency();
    $payee = AccountEntity::factory()->asPayee($user)->create(['active' => false]);

    $this->actingAs($user)->get(route('account-entity.show', $payee))->assertOk();

    expect(app(AssetOverviewService::class)->payeeOverview($user, $payee))->toMatchArray([
        'count' => 0,
        'first_date' => null,
        'last_date' => null,
        'withdrawal_total' => '0.0000',
        'deposit_total' => '0.0000',
    ]);
});

it('splits payee totals into withdrawals and deposits and ignores schedules', function () {
    [$user] = userWithBaseCurrency();
    $payee = AccountEntity::factory()->asPayee($user)->create();
    $category = Category::factory()->for($user)->create();

    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-01-10', [[$category->id, 30], [$category->id, 20]]);
    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-03-05', [[$category->id, 100]]);
    makeStandardTransaction($user, $payee, TransactionType::DEPOSIT, '2024-02-01', [[$category->id, 7]]);
    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-06-01', [[$category->id, 999]], schedule: true);

    $this->actingAs($user);
    $overview = app(AssetOverviewService::class)->payeeOverview($user, $payee);

    expect($overview)->toMatchArray([
        'count' => 3,
        'first_date' => '2024-01-10',
        'last_date' => '2024-03-05',
        'withdrawal_total' => '150.0000',
        'deposit_total' => '7.0000',
    ]);
});

it('converts foreign currency amounts to the base currency with the monthly rate', function () {
    [$user, $base] = userWithBaseCurrency();
    $foreign = Currency::factory()->for($user)->create(['base' => null]);
    CurrencyRate::factory()->create(['from_id' => $foreign->id, 'to_id' => $base->id, 'date' => '2024-01-15', 'rate' => 2]);
    $payee = AccountEntity::factory()->asPayee($user)->create();
    $category = Category::factory()->for($user)->create();

    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-01-20', [[$category->id, 10]], $foreign);
    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-01-21', [[$category->id, 5]], $base);

    $this->actingAs($user);

    expect(app(AssetOverviewService::class)->payeeOverview($user, $payee)['withdrawal_total'])->toBe('25.0000');
});

it('shows the category page to its owner', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('categories.show', $category))
        ->assertOk()
        ->assertViewIs('categories.show');
});

it('forbids showing another user\'s category', function () {
    $category = Category::factory()->for(User::factory()->create())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('categories.show', $category))
        ->assertForbidden();
});

it('keeps the category merge form reachable next to the show route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('categories.merge.form'))->assertOk()->assertViewIs('categories.merge');
});

it('counts only the matching item of a split transaction and includes children in category totals', function () {
    [$user] = userWithBaseCurrency();
    $payee = AccountEntity::factory()->asPayee($user)->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->create(['parent_id' => $parent->id]);
    $other = Category::factory()->for($user)->create();

    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-01-10', [[$parent->id, 40], [$other->id, 500]]);
    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-02-10', [[$child->id, 60]]);
    makeStandardTransaction($user, $payee, TransactionType::DEPOSIT, '2024-03-10', [[$child->id, 5]]);
    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-04-10', [[$child->id, 999]], schedule: true);

    $this->actingAs($user);
    $service = app(AssetOverviewService::class);

    expect($service->categoryOverview($user, $parent))->toMatchArray([
        'count' => 3,
        'first_date' => '2024-01-10',
        'last_date' => '2024-03-10',
        'withdrawal_total' => '100.0000',
        'deposit_total' => '5.0000',
    ])->and($service->categoryOverview($user, $child)['withdrawal_total'])->toBe('60.0000');
});

it('lists category budgets with their next occurrence', function () {
    [$user] = userWithBaseCurrency();
    $category = Category::factory()->for($user)->create();
    $budget = Budget::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'frequency' => 'YEARLY',
        'interval' => 1,
        'start_date' => now()->addMonths(3)->toDateString(),
    ]);

    $budgets = app(AssetOverviewService::class)->categoryBudgets($category);

    expect($budgets)->toHaveCount(1)
        ->and($budgets->first()->id)->toBe($budget->id)
        ->and($budgets->first()->next_occurrence)->toBe($budget->start_date->toDateString());
});

it('counts a transaction once even if several of its items are in the category', function () {
    [$user] = userWithBaseCurrency();
    $payee = AccountEntity::factory()->asPayee($user)->create();
    $category = Category::factory()->for($user)->create();

    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-01-10', [[$category->id, 10], [$category->id, 20]]);

    $this->actingAs($user);

    expect(app(AssetOverviewService::class)->categoryOverview($user, $category))
        ->toMatchArray(['count' => 1, 'withdrawal_total' => '30.0000']);
});

it('flags totals that include a foreign currency without an exchange rate', function () {
    [$user] = userWithBaseCurrency();
    $foreign = Currency::factory()->for($user)->create(['base' => null]);
    $payee = AccountEntity::factory()->asPayee($user)->create();
    $category = Category::factory()->for($user)->create();

    makeStandardTransaction($user, $payee, TransactionType::WITHDRAWAL, '2024-01-20', [[$category->id, 10]], $foreign);

    $this->actingAs($user);

    expect(app(AssetOverviewService::class)->payeeOverview($user, $payee)['rates_missing'])->toBeTrue();
});

it('does not expose show routes for resources without a show page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/tags/1')->assertMethodNotAllowed();
    $this->actingAs($user)->get('/currencies/1')->assertMethodNotAllowed();
});
