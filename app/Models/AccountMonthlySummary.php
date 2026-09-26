<?php

/**
 * This might not qualify as a model, and should be a simple database operation with the necessary helpers,
 * but it seems to be convenient to to utilize model capabilities for it. We'll see how it goes.
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Http\Traits\CurrencyTrait;
use App\Jobs\CalculateAccountMonthlySummary;
use App\Services\InvestmentService;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $user_id
 * @property int|null $account_entity_id
 * @property string $transaction_type
 * @property string $data_type
 * @property-read Money $amount
 * @property-write Money|string|int|float $amount
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read AccountEntity|null $accountEntity
 * @property-read User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereAccountEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereTransactionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereUserId($value)
 * @mixin Eloquent
 */
#[Fillable('date', 'user_id', 'account_entity_id', 'transaction_type', 'data_type', 'amount')]
class AccountMonthlySummary extends Model
{
    use CurrencyTrait;

    // This model is not using the created_at column, only the updated_at column
    public const null CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => MoneyCast::class . ':4,resolveAmountCurrency',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountEntity(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class);
    }

    /**
     * amount is always in the currency of the account, or the base currency for
     * generic budgets (account_entity_id is nullable for those).
     */
    public function resolveAmountCurrency(): Currency
    {
        $config = $this->loadMissing('accountEntity.config')->accountEntity?->config;

        if ($config instanceof Account) {
            return $config->loadMissing('currency')->currency;
        }

        $currency = $this->getBaseCurrency($this->user_id);

        if ($currency === null) {
            throw new RuntimeException('Unable to resolve a currency for this account monthly summary: no account and no base currency available.');
        }

        return $currency;
    }

    /**
     * Whether a CalculateAccountMonthlySummary batch for this user is still running, i.e. any
     * report reading this table right now could see an incomplete recalculation. Optionally
     * narrowed to specific tasks (e.g. only the fact tasks a fact-only report reads), otherwise
     * matches any task. Shared by every reader (account balance, cash flow report) so the
     * "in progress" and staleness rules can't drift between them the way they used to.
     *
     * A batch older than 1 hour is treated as orphaned/stuck rather than genuinely in progress,
     * since these jobs normally complete within seconds.
     *
     * @param  string[]  $tasks  e.g. ['account_balance-fact', 'investment_value-fact']. Empty matches any task.
     */
    public static function isCalculationInProgress(int $userId, array $tasks = []): bool
    {
        return DB::table('job_batches')
            ->when(
                $tasks !== [],
                fn ($query) => $query->whereIn(
                    'name',
                    array_map(
                        fn (string $task) => CalculateAccountMonthlySummary::batchName($userId, $task),
                        $tasks
                    )
                ),
                fn ($query) => $query->where('name', 'like', CalculateAccountMonthlySummary::batchName($userId, '%'))
            )
            ->whereNull('finished_at')
            ->where('created_at', '>', now()->subHour()->getTimestamp())
            ->exists();
    }

    /**
     * Helper function to recalculate the summary fact for a given month,
     * for standard transactions, for a specified account.
     */
    public static function calculateAccountBalanceFact(AccountEntity $accountEntity, Carbon $month): BigDecimal
    {
        // New variables cloned from the date as start and end of the month
        $startOfMonth = $month->clone()->startOfMonth();
        $endOfMonth = $month->clone()->endOfMonth();

        // Get the sum of all the standard transactions for the given month for this account.
        // Uses rawValue() with a raw SUM() rather than the ->sum() shorthand: the shorthand
        // routes through Builder::numericAggregate(), which float-casts any decimal-point
        // result, silently losing precision before it ever reaches BigDecimal.
        $valueFrom = DB::table('transactions')
            ->join(
                'transaction_details_standard',
                'transactions.config_id',
                '=',
                'transaction_details_standard.id'
            )
            ->where('config_type', 'standard')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('schedule', 0)
            ->where('transaction_details_standard.account_from_id', $accountEntity->id)
            ->rawValue('SUM(transaction_details_standard.amount_from)') ?? '0';

        $valueTo = DB::table('transactions')
            ->join(
                'transaction_details_standard',
                'transactions.config_id',
                '=',
                'transaction_details_standard.id'
            )
            ->where('config_type', 'standard')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('schedule', 0)
            ->where('transaction_details_standard.account_to_id', $accountEntity->id)
            ->rawValue('SUM(transaction_details_standard.amount_to)') ?? '0';

        // Get the cash flow value for all investment transactions for the given month for this account
        $valueInvestment = DB::table('transactions')
            ->join(
                'transaction_details_investment',
                'transactions.config_id',
                '=',
                'transaction_details_investment.id'
            )
            ->where('config_type', 'investment')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('schedule', 0)
            ->where('transaction_details_investment.account_id', $accountEntity->id)
            ->rawValue('SUM(cashflow_value)') ?? '0';

        // The three raw SQL sums are exact DECIMAL strings from PDO - combine them via
        // BigDecimal instead of native float arithmetic, the same repeated-summation
        // drift pattern AMOUNT_COMPARISON_EPSILON was invented to tolerate (FR-1), one
        // layer further downstream.
        return BigDecimal::of($valueInvestment)->plus($valueTo)->minus($valueFrom);
    }

    /**
     * This is a helper function to calculate the value of investments owned at the end of a given month,
     * for a given account. Under the hood, we need to get the quantity up to this date, and the latest
     * known price up to this date.
     *
     * @param  array<string, BigDecimal|null>|null  $priceMap  Optional pre-fetched batch of
     *                                                          InvestmentService::getLatestPricesBatchExact() results, keyed by
     *                                                          InvestmentService::priceBatchKey(). When provided, this skips the
     *                                                          per-investment Investment::find()/getLatestPriceExact() N+1 that a
     *                                                          caller resolving many months at once (e.g. CalculateAccountMonthlySummary)
     *                                                          would otherwise pay once per investment per month.
     */
    public static function calculateInvestmentValueFact(AccountEntity $accountEntity, Carbon $date, ?array $priceMap = null): BigDecimal
    {
        // New variable cloned from the date as the end of the target month
        $endOfMonth = $date->clone()->endOfMonth();

        /** @var Account $account $investments */
        $account = $accountEntity->config;

        // Get the associated quantity of all the investment transactions for the given month for this account
        $investments = $account->getAssociatedInvestmentsAndQuantity($endOfMonth);

        // All associated investments should be in the same currency as the account, so no conversion is needed
        // Get the current value of all the investments
        $investmentService = app(InvestmentService::class);

        $sum = BigDecimal::zero();

        foreach ($investments as $item) {
            // $item->quantity is a raw SUM() of a DECIMAL column via DB::table(), so it
            // arrives as a numeric string, not a float - build the BigDecimal from it directly.
            $quantity = BigDecimal::of($item->quantity);

            if ($quantity->isZero()) {
                continue;
            }

            if ($priceMap !== null) {
                $price = $priceMap[$investmentService->priceBatchKey((int) $item->investment_id, $endOfMonth)] ?? null;
            } else {
                $investment = Investment::find($item->investment_id);
                $price = $investmentService->getLatestPriceExact($investment, 'combined', $endOfMonth);
            }

            if ($price === null) {
                continue;
            }

            $sum = $sum->plus($quantity->multipliedBy($price));
        }

        return $sum;
    }
}
