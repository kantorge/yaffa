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
use App\Services\CategoryWaterfallCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    /**
     * The waterfall query is now cached (see CategoryWaterfallCacheService): identical
     * requests must not re-hit the DB, but a cache entry must also actually go stale once
     * CategoryWaterfallCacheService::forgetForDate() is invoked for its period - which is
     * what every TransactionCreated/Updated/Deleted listener now does.
     */
    public function test_waterfall_response_is_cached_until_explicitly_invalidated(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $account = $this->createAccount($this->baseCurrency);
        $category = Category::factory()->for($this->user)->create();

        $transactionConfig = TransactionDetailStandard::factory()
            ->withdrawal($this->user)
            ->create(['account_from_id' => $account->id]);

        $transaction = Transaction::factory()
            ->for($this->user)
            ->create([
                'schedule' => false,
                'date' => '2025-01-10',
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
                'config_type' => 'standard',
                'config_id' => $transactionConfig->id,
            ]);

        $transaction->transactionItems()->delete();
        $item = TransactionItem::factory()->for($transaction)->create(['category_id' => $category->id, 'amount' => 10]);

        $routeParams = [
            'transactionType' => 'standard',
            'dataType' => 'result',
            'year' => 2025,
            'month' => 1,
        ];

        $bucketValue = fn () => collect($this->getJson(route('api.v1.reports.waterfall', $routeParams))->json('chartData'))
            ->firstWhere('category_id', $category->id)['value'];

        $this->assertEquals(-10.0, $bucketValue());

        $cacheKey = CategoryWaterfallCacheService::key($this->user->id, 'standard', 'result', 2025, 1);
        $this->assertTrue(Cache::has($cacheKey));

        // Change the underlying data without going through the API (so no invalidating
        // event fires) - the cached response must still be served.
        $item->update(['amount' => 25]);
        $this->assertEquals(-10.0, $bucketValue());

        CategoryWaterfallCacheService::forgetForDate($this->user->id, $transaction->date);
        $this->assertFalse(Cache::has($cacheKey));

        $this->assertEquals(-25.0, $bucketValue());
    }

    /**
     * A Category edit (rename/reparent/delete) isn't scoped to one transaction date the way
     * a Transaction change is, so CategoryObserver invalidates the whole per-user cache via
     * CategoryWaterfallCacheService::forgetAllForUser(), which bumps a per-user version
     * embedded in key() rather than deleting the old key outright (see that method's
     * docblock) - so the assertion here is that key() starts pointing at a fresh,
     * not-yet-cached entry, not that the old literal key is gone.
     */
    public function test_waterfall_cache_key_changes_when_category_is_updated(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $account = $this->createAccount($this->baseCurrency);
        $category = Category::factory()->for($this->user)->create(['name' => 'Original']);

        $transactionConfig = TransactionDetailStandard::factory()
            ->withdrawal($this->user)
            ->create(['account_from_id' => $account->id]);

        $transaction = Transaction::factory()
            ->for($this->user)
            ->create([
                'schedule' => false,
                'date' => '2025-01-10',
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
                'config_type' => 'standard',
                'config_id' => $transactionConfig->id,
            ]);
        $transaction->transactionItems()->delete();
        TransactionItem::factory()->for($transaction)->create(['category_id' => $category->id, 'amount' => 10]);

        $routeParams = ['transactionType' => 'standard', 'dataType' => 'result', 'year' => 2025, 'month' => 1];
        $this->getJson(route('api.v1.reports.waterfall', $routeParams))->assertOk();

        $cacheKeyBefore = CategoryWaterfallCacheService::key($this->user->id, 'standard', 'result', 2025, 1);
        $this->assertTrue(Cache::has($cacheKeyBefore));

        $category->update(['name' => 'Renamed']);

        $cacheKeyAfter = CategoryWaterfallCacheService::key($this->user->id, 'standard', 'result', 2025, 1);
        $this->assertNotEquals($cacheKeyBefore, $cacheKeyAfter);
        $this->assertFalse(Cache::has($cacheKeyAfter));
    }
}
