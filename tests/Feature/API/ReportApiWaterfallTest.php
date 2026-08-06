<?php

namespace Tests\Feature\API;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiWaterfallTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $baseCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->baseCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);
    }

    private function createAccount(Currency $currency): AccountEntity
    {
        return AccountEntity::factory()
            ->for($this->user)
            ->for(
                Account::factory()->withUser($this->user)->create([
                    'currency_id' => $currency->id,
                    'opening_balance' => 0,
                ]),
                'config'
            )
            ->create();
    }

    public function test_waterfall_reports_missing_foreign_currency_rates_in_warnings_payload(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $foreignCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        $foreignAccount = $this->createAccount($foreignCurrency);

        $transactionConfig = TransactionDetailStandard::factory()
            ->withdrawal($this->user)
            ->create([
                'account_from_id' => $foreignAccount->id,
            ]);

        Transaction::factory()
            ->for($this->user)
            ->create([
                'schedule' => false,
                'date' => '2025-01-10',
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
                'config_type' => 'standard',
                'config_id' => $transactionConfig->id,
            ]);

        $response = $this->getJson(route('api.v1.reports.waterfall', [
            'transactionType' => 'standard',
            'dataType' => 'result',
            'year' => 2025,
            'month' => 1,
        ]));

        $response->assertOk();
        $response->assertJsonPath('result', 'success');
        $response->assertJsonPath('warnings.currenciesWithoutRates.0.iso_code', 'EUR');
        $response->assertJsonPath('warnings.currenciesWithoutRates.0.name', $foreignCurrency->name);
    }

    /**
     * Regression test for the precision-improvements FR-7 follow-up: the category bucket
     * used to accumulate item amounts via a plain float `+=`, the same repeated-summation
     * drift pattern AMOUNT_COMPARISON_EPSILON was invented to tolerate elsewhere. 0.10 and
     * 0.20 are a classic IEEE 754 case (0.10 + 0.20 !== 0.30 in native float arithmetic).
     */
    public function test_waterfall_standard_category_sums_transaction_item_amounts_exactly(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $account = $this->createAccount($this->baseCurrency);
        $category = Category::factory()->for($this->user)->create();

        $transactionConfig = TransactionDetailStandard::factory()
            ->withdrawal($this->user)
            ->create([
                'account_from_id' => $account->id,
            ]);

        $transaction = Transaction::factory()
            ->for($this->user)
            ->create([
                'budget' => false,
                'schedule' => false,
                'date' => '2025-01-10',
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
                'config_type' => 'standard',
                'config_id' => $transactionConfig->id,
            ]);

        // Replace the factory's randomly-generated item(s) with two precise amounts in the
        // same category.
        $transaction->transactionItems()->delete();
        TransactionItem::factory()->for($transaction)->create(['category_id' => $category->id, 'amount' => 0.10]);
        TransactionItem::factory()->for($transaction)->create(['category_id' => $category->id, 'amount' => 0.20]);

        $response = $this->getJson(route('api.v1.reports.waterfall', [
            'transactionType' => 'standard',
            'dataType' => 'result',
            'year' => 2025,
            'month' => 1,
        ]));

        $response->assertOk();

        $bucket = collect($response->json('chartData'))->firstWhere('category_id', $category->id);

        $this->assertNotNull($bucket);
        // Withdrawal => negative sign; must be exactly -0.30, not a float-drift artifact.
        $this->assertSame(-0.30, $bucket['value']);
    }
}
