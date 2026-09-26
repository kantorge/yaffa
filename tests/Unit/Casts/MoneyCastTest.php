<?php

namespace Tests\Unit\Casts;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MoneyCast round-trip and serialization coverage, exercised via TransactionItem::amount
 * (a real Money-cast field), plus the currency-mismatch guard brick/money provides for free.
 */
class MoneyCastTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function createItemWithAmount(float|string $amount): \App\Models\TransactionItem
    {
        $transaction = Transaction::factory()->withdrawal($this->user)->create();
        $transaction->transactionItems()->delete();

        $category = Category::factory()->for($this->user)->create(['parent_id' => null]);
        $child = Category::factory()->for($this->user)->create(['parent_id' => $category->id]);

        return $transaction->transactionItems()->create([
            'category_id' => $child->id,
            'amount' => $amount,
        ]);
    }

    public function test_get_returns_a_money_instance_at_the_columns_scale(): void
    {
        $item = $this->createItemWithAmount('42.5');

        $fresh = $item->fresh();

        $this->assertInstanceOf(Money::class, $fresh->amount);
        $this->assertSame('42.5000', (string) $fresh->amount->getAmount());
    }

    public function test_get_resolves_the_parent_transactions_currency(): void
    {
        $item = $this->createItemWithAmount('10');

        $fresh = $item->fresh();
        $expectedCurrency = $fresh->transaction->transaction_currency;

        $this->assertSame($expectedCurrency->iso_code, $fresh->amount->getCurrency()->getCurrencyCode());
    }

    public function test_set_accepts_a_plain_scalar_and_stores_it_at_the_exact_scale(): void
    {
        $item = $this->createItemWithAmount(12.3);

        $this->assertSame('12.3000', $item->getRawOriginal('amount'));
    }

    public function test_serialize_emits_a_decimal_string_not_a_float(): void
    {
        $item = $this->createItemWithAmount('7.25')->fresh();

        $array = $item->toArray();

        $this->assertIsString($array['amount']);
        $this->assertSame('7.2500', $array['amount']);

        $json = $item->toJson();
        $this->assertStringContainsString('"amount":"7.2500"', $json);
        $this->assertStringNotContainsString('"amount":7.25', $json);
    }

    public function test_nullable_money_field_round_trips_as_null(): void
    {
        // dividend is nullable (unlike amount) - buy() leaves it unset.
        $transaction = Transaction::factory()->buy($this->user)->create();

        $this->assertNull($transaction->fresh()->config->dividend);
    }

    /**
     * Account::opening_balance (FR-7) round-trips through its own currency, at the
     * column's scale (10), and serializes as a decimal string.
     */
    public function test_account_opening_balance_round_trips_and_serializes_as_a_string(): void
    {
        $currency = Currency::factory()->for($this->user)->create();
        $account = Account::factory()->withUser($this->user)->create([
            'opening_balance' => '1000.5',
            'currency_id' => $currency->id,
        ]);

        $fresh = $account->fresh();

        $this->assertInstanceOf(Money::class, $fresh->opening_balance);
        $this->assertSame('1000.5000000000', (string) $fresh->opening_balance->getAmount());
        $this->assertSame($currency->iso_code, $fresh->opening_balance->getCurrency()->getCurrencyCode());

        $array = $fresh->toArray();
        $this->assertSame('1000.5000000000', $array['opening_balance']);
    }

    /**
     * Transaction::cashflow_value (FR-7) round-trips through the transaction's own
     * currency and serializes as a decimal string.
     */
    public function test_transaction_cashflow_value_round_trips_and_serializes_as_a_string(): void
    {
        // TransactionFactory::configure() recomputes cashflow_value from the actual
        // withdrawal items after creation, so assert round-trip consistency against
        // whatever it computed rather than a hardcoded value.
        $transaction = Transaction::factory()->withdrawal($this->user)->create();

        $fresh = $transaction->fresh();
        $rawValue = $fresh->getRawOriginal('cashflow_value');

        $this->assertInstanceOf(Money::class, $fresh->cashflow_value);
        $this->assertSame($rawValue, (string) $fresh->cashflow_value->getAmount());

        $array = $fresh->toArray();
        $this->assertSame($rawValue, $array['cashflow_value']);
    }

    /**
     * AccountMonthlySummary::amount (FR-7) uses the account's currency when
     * account_entity_id is set...
     */
    public function test_account_monthly_summary_amount_uses_the_accounts_currency(): void
    {
        $currency = Currency::factory()->for($this->user)->create();
        $account = Account::factory()->withUser($this->user)->create(['currency_id' => $currency->id]);
        $accountEntity = AccountEntity::factory()->for($this->user)->for($account, 'config')->create();

        $summary = AccountMonthlySummary::create([
            'date' => now()->startOfMonth(),
            'user_id' => $this->user->id,
            'account_entity_id' => $accountEntity->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => '100',
        ]);

        $this->assertSame($currency->iso_code, $summary->fresh()->amount->getCurrency()->getCurrencyCode());
    }

    /**
     * ...and falls back to the user's base currency for generic budgets, which have no
     * account_entity_id.
     */
    public function test_account_monthly_summary_amount_falls_back_to_base_currency_without_an_account(): void
    {
        $baseCurrency = Currency::factory()->for($this->user)->create(['base' => true]);

        $summary = AccountMonthlySummary::create([
            'date' => now()->startOfMonth(),
            'user_id' => $this->user->id,
            'account_entity_id' => null,
            'transaction_type' => 'account_balance',
            'data_type' => 'budget',
            'amount' => '50',
        ]);

        $this->assertSame($baseCurrency->iso_code, $summary->fresh()->amount->getCurrency()->getCurrencyCode());
    }

    /**
     * brick/money enforces that arithmetic combining two Money instances of different
     * currencies throws, rather than silently producing a wrong total - this is the
     * guard FR-5 relies on when combining price (investment currency) with
     * dividend/tax/commission (account currency) for a corrupted-data scenario.
     */
    public function test_combining_money_of_different_currencies_throws(): void
    {
        $usd = Money::of('10.00', new BrickCurrency('USD', 0, 'USD', 4));
        $eur = Money::of('5.00', new BrickCurrency('EUR', 0, 'EUR', 4));

        $this->expectException(MoneyMismatchException::class);

        $usd->plus($eur, RoundingMode::HalfUp);
    }
}
