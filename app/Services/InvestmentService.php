<?php

namespace App\Services;

use App\Casts\DecimalCast;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Events\InvestmentPricesUpdated;
use App\Exceptions\PriceProviderException;
use App\Http\Traits\ScheduleTrait;
use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Support\ScheduleInstance;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvestmentService
{
    use ScheduleTrait;

    public function __construct(
        private InvestmentPriceProviderContextResolver $contextResolver
    ) {
    }

    public function delete(Investment $investment): array
    {
        if ($investment->transactionDetailInvestment()->exists()) {
            return [
                'success' => false,
                'error' => __('Investment is in use, cannot be deleted'),
            ];
        }

        $success = false;
        $error = null;

        try {
            $success = (bool) $investment->delete();

            if (! $success) {
                $error = __('Investment could not be deleted');
            }
        } catch (Throwable $e) {
            report($e);
            $error = __('Database error:') . ' ' . $e->getMessage();
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }

    /**
     * Recalculate monthly summaries for all accounts related to this investment.
     *
     * This method is called after investment price changes to ensure that:
     * - The current value of investment holdings is accurate
     * - Account balance displays reflect updated investment valuations
     * - Monthly summaries show correct investment values based on latest prices
     *
     * The recalculation process:
     * 1. Finds all accounts holding this investment (via TransactionDetailInvestment)
     * 2. Dispatches CalculateAccountMonthlySummary jobs for each account directly
     *
     * Dispatching directly (rather than via Artisan) ensures the recalculation is triggered
     * reliably for every successful price fetch, including rate-limited jobs that retry later.
     *
     * @param Investment $investment The investment whose related accounts need recalculation
     */
    public function recalculateRelatedAccounts(Investment $investment): void
    {
        $accountIds = TransactionDetailInvestment::where('investment_id', $investment->id)
            ->distinct()
            ->pluck('account_id');

        $accountIds->each(function (int $accountId) {
            /** @var AccountEntity|null $accountEntity */
            $accountEntity = AccountEntity::with('user')->find($accountId);

            if ($accountEntity === null) {
                return;
            }

            Bus::batch([
                new CalculateAccountMonthlySummary($accountEntity->user, 'investment_value-fact', $accountEntity),
                new CalculateAccountMonthlySummary($accountEntity->user, 'investment_value-forecast', $accountEntity),
            ])->dispatch();
        });
    }

    public function enrichInvestmentWithQuantityHistory(Investment $investment): Investment
    {
        $transactions = $investment->transactionsBasic()->get();
        $scheduledTransactions = $investment->transactionsScheduled()
            ->get()
            // scheduleInstances() reuses whichever relations are already loaded on the source
            // transaction (see App\Support\ScheduleInstance) instead of lazy-loading per virtual
            // occurrence, so 'config' must be eager-loaded here up front.
            ->load(['config', 'transactionSchedule'])
            ->filter(
                fn ($transaction): bool => $transaction instanceof Transaction
                    && ($transaction->transactionSchedule?->active) === true
            );

        // Add all scheduled items to list of transactions
        $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'next');
        $transactions = $transactions->concat($scheduleInstances);

        // Calculate historical and scheduled quantity changes for chart
        $runningTotal = 0;
        $runningSchedule = 0;
        $quantities = $transactions
            ->sortBy('date')
            ->map(function (Transaction|ScheduleInstance $transaction) use (&$runningTotal, &$runningSchedule) {
                // Quantity operator can be 1, -1 or null.
                // It's the expected behavior to set the quantity to 0 if the operator is null.
                $transactionConfig = $transaction->config;

                if (! $transactionConfig instanceof TransactionDetailInvestment) {
                    $quantity = 0.0;
                } else {
                    $quantity = $transaction->transaction_type->quantityMultiplier()
                        * (DecimalCast::toFloat($transactionConfig->quantity) ?? 0.0);
                }

                $runningSchedule += $quantity;
                if (!$transaction->schedule) {
                    $runningTotal += $quantity;
                }

                return [
                    'date' => $transaction->date->format('Y-m-d'),
                    'quantity' => $runningTotal,
                    'schedule' => $runningSchedule,
                ];
            });

        $investment->quantities = array_values($quantities->toArray());

        return $investment;
    }

    /**
     * Fetch and save investment prices from configured provider
     * Replaces Investment->getInvestmentPriceFromProvider()
     *
     * @param  Investment  $investment  The investment to fetch prices for
     * @param  Carbon|null  $from  Start date for price history (null = auto-determine)
     * @param  bool  $refill  Whether to fetch full history vs. incremental
     * @return array Array of price data that was saved
     *
     * @throws PriceProviderException
     */
    public function fetchAndSavePrices(Investment $investment, ?Carbon $from = null, bool $refill = false): array
    {
        $context = $this->contextResolver->resolve($investment);

        $provider = $context['provider'];

        $investmentSettings = $context['investment_settings'];
        $credentials = $context['credentials'];

        $originalProviderSettings = $investment->provider_settings;
        $originalProviderCredentials = $investment->provider_credentials;

        $investment->provider_settings = $investmentSettings;
        $investment->provider_credentials = $credentials;

        try {
            $prices = $provider->fetchPrices($investment, $from, $refill);
        } finally {
            $investment->provider_settings = $originalProviderSettings;
            $investment->provider_credentials = $originalProviderCredentials;
        }

        foreach ($prices as $priceData) {
            $this->savePriceQuietly($investment, $priceData['date'], $priceData['price']);
        }

        if (count($prices) > 0) {
            event(new InvestmentPricesUpdated($investment));
        }

        return $prices;
    }

    public function markPriceFetchAttempted(Investment $investment): void
    {
        $investment->forceFill([
            'last_price_fetch_attempted_at' => now(),
        ])->save();
    }

    public function markPriceFetchSucceeded(Investment $investment): void
    {
        $investment->forceFill([
            'last_price_fetch_succeeded_at' => now(),
            'last_price_fetch_error_at' => null,
            'last_price_fetch_error_message' => null,
        ])->save();
    }

    public function markPriceFetchFailed(Investment $investment, string $errorMessage): void
    {
        $investment->forceFill([
            'last_price_fetch_error_at' => now(),
            'last_price_fetch_error_message' => mb_substr($errorMessage, 0, 65000),
        ])->save();
    }

    public function markPreflightValidationFailed(Investment $investment, string $errorMessage): void
    {
        $investment->forceFill([
            'last_price_fetch_error_at' => now(),
            'last_price_fetch_error_message' => mb_substr($errorMessage, 0, 65000),
        ])->save();
    }

    /**
     * Get current quantity across all or specific account
     * Replaces Investment->getCurrentQuantity()
     *
     * @param  Investment  $investment  The investment to calculate quantity for
     * @param  AccountEntity|null  $account  Optional account filter
     * @return float The current quantity
     */
    public function getCurrentQuantity(Investment $investment, ?AccountEntity $account = null): float
    {
        $quantity = DB::table('transactions')
            ->select(
                DB::raw(
                    'SUM( ' .
                        TransactionTypeEnum::getQuantityMultiplierSqlCase('transactions.transaction_type') .
                        ' * IFNULL(transaction_details_investment.quantity, 0)
                    ) AS quantity'
                )
            )
            ->leftJoin(
                'transaction_details_investment',
                'transactions.config_id',
                '=',
                'transaction_details_investment.id'
            )
            ->where('transactions.schedule', 0)
            ->where('transactions.config_type', 'investment')
            ->where('transaction_details_investment.investment_id', $investment->id)
            ->when($account !== null, function ($query) use ($account) {
                $query->where('transaction_details_investment.account_id', '=', $account->id);
            })
            ->get();

        return $quantity->first()->quantity ?? 0;
    }

    /**
     * Get latest price using specified strategy
     * Replaces Investment->getLatestPrice()
     *
     * A thin float-collapsing wrapper around getLatestPriceExact(), for display-only
     * consumers (e.g. InvestmentController) that don't accumulate this value.
     *
     * @param  Investment  $investment  The investment to get price for
     * @param  string  $type  Can be 'stored', 'transaction' or 'combined'
     * @param  Carbon|null  $onOrBefore  Optional date filter
     * @return float|null The price or null if not found
     */
    public function getLatestPrice(Investment $investment, string $type = 'combined', ?Carbon $onOrBefore = null): ?float
    {
        return $this->getLatestPriceExact($investment, $type, $onOrBefore)?->toFloat();
    }

    /**
     * Exact-arithmetic counterpart of getLatestPrice(): same resolution strategy (stored
     * price, priced transaction, or whichever of the two is newer), but returns the
     * underlying BigDecimal instead of collapsing to a float, for callers that accumulate
     * this value (e.g. CalculateAccountMonthlySummary) rather than just displaying it.
     *
     * @param  Investment  $investment  The investment to get price for
     * @param  string  $type  Can be 'stored', 'transaction' or 'combined'
     * @param  Carbon|null  $onOrBefore  Optional date filter
     */
    public function getLatestPriceExact(Investment $investment, string $type = 'combined', ?Carbon $onOrBefore = null): ?BigDecimal
    {
        if ($type === 'stored') {
            $price = $this->getLatestStoredPrice($investment, $onOrBefore);
            $price?->setRelation('investment', $investment);

            return $price instanceof InvestmentPrice ? $price->price->getAmount() : null;
        }

        if ($type === 'transaction') {
            $transaction = $this->getLatestTransactionWithPrice($investment, $onOrBefore);

            return $this->extractTransactionPrice($transaction);
        }

        // Proceed with combined price
        return $this->getLatestCombinedPrice($investment, $onOrBefore);
    }

    /**
     * Get latest stored price from investment_prices table
     *
     * @param  Investment  $investment  The investment to get price for
     * @param  Carbon|null  $onOrBefore  Optional date filter
     * @return InvestmentPrice|null The price record or null
     */
    private function getLatestStoredPrice(Investment $investment, ?Carbon $onOrBefore = null): ?InvestmentPrice
    {
        return InvestmentPrice::where('investment_id', $investment->id)
            ->with('investment.currency')
            ->when($onOrBefore, function ($query) use ($onOrBefore) {
                $query->where('date', '<=', $onOrBefore);
            })
            ->latest('date')
            ->first();
    }

    /**
     * Get latest transaction with price
     *
     * @param  Investment  $investment  The investment to get transaction for
     * @param  Carbon|null  $onOrBefore  Optional date filter
     * @return Transaction|null The transaction or null
     */
    private function getLatestTransactionWithPrice(Investment $investment, ?Carbon $onOrBefore = null): ?Transaction
    {
        return Transaction::with([
            'config',
        ])
            ->where('schedule', false)
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($investment) {
                    $query
                        ->where('investment_id', $investment->id)
                        ->whereNotNull('price');
                }
            )
            ->when($onOrBefore, function ($query) use ($onOrBefore) {
                $query->where('date', '<=', $onOrBefore);
            })
            ->latest('date')
            ->first();
    }

    /**
     * Get latest price using combined strategy (stored + transaction fallback)
     *
     * @param  Investment  $investment  The investment to get price for
     * @param  Carbon|null  $onOrBefore  Optional date filter
     */
    private function getLatestCombinedPrice(Investment $investment, ?Carbon $onOrBefore = null): ?BigDecimal
    {
        $price = $this->getLatestStoredPrice($investment, $onOrBefore);
        $price?->setRelation('investment', $investment);

        $transaction = $this->getLatestTransactionWithPrice($investment, $onOrBefore);
        if ($transaction?->config instanceof TransactionDetailInvestment) {
            $transaction->config->setRelation('investment', $investment);
        }

        return $this->resolveCombinedPrice($price, $transaction);
    }

    /**
     * Pick the winning price between a stored price row and a priced transaction,
     * whichever is newer. Shared by the single-lookup and batch-lookup paths.
     */
    private function resolveCombinedPrice(?InvestmentPrice $price, ?Transaction $transaction): ?BigDecimal
    {
        if (($price instanceof InvestmentPrice) && ($transaction instanceof Transaction)) {
            if ($price->date > $transaction->date) {
                return $price->price->getAmount();
            }

            return $this->extractTransactionPrice($transaction);
        }

        // We have only stored data
        if ($price instanceof InvestmentPrice) {
            return $price->price->getAmount();
        }

        // We have only transaction data
        if ($transaction instanceof Transaction) {
            return $this->extractTransactionPrice($transaction);
        }

        return null;
    }

    /**
     * Build a stable lookup key for a (investment, as-of date) price request, used by
     * getLatestPricesBatch().
     */
    public function priceBatchKey(int $investmentId, ?Carbon $onOrBefore): string
    {
        return $investmentId . '|' . ($onOrBefore?->format('Y-m-d') ?? '');
    }

    /**
     * Resolve the "combined" latest price (stored price vs. priced transaction, whichever
     * is newer) for many (investment, as-of date) pairs at once.
     *
     * Replaces N calls to getLatestPrice('combined', ...) - each of which issues up to 2
     * queries - with 2 bulk queries total, regardless of how many investments or holding
     * periods are being resolved. Intended for report/timeline views that need the price
     * as of many different dates across a user's whole portfolio.
     *
     * A thin float-collapsing wrapper around getLatestPricesBatchExact(), for display-only
     * consumers that don't accumulate this value.
     *
     * @param  Collection<int, array{investment: Investment, date: Carbon|null}>  $requests
     * @return array<string, float|null> Map keyed by priceBatchKey() to the resolved price
     */
    public function getLatestPricesBatch(Collection $requests): array
    {
        return array_map(
            fn (?BigDecimal $price) => $price?->toFloat(),
            $this->getLatestPricesBatchExact($requests)
        );
    }

    /**
     * Exact-arithmetic counterpart of getLatestPricesBatch(): same batched resolution, but
     * returns each price as a BigDecimal instead of collapsing it to a float, for callers
     * that accumulate this value (e.g. CalculateAccountMonthlySummary) rather than just
     * displaying it.
     *
     * @param  Collection<int, array{investment: Investment, date: Carbon|null}>  $requests
     * @return array<string, BigDecimal|null> Map keyed by priceBatchKey() to the resolved price
     */
    public function getLatestPricesBatchExact(Collection $requests): array
    {
        if ($requests->isEmpty()) {
            return [];
        }

        $investmentIds = $requests->pluck('investment.id')->unique()->values();

        $storedPricesByInvestment = InvestmentPrice::whereIn('investment_id', $investmentIds)
            ->orderBy('date')
            ->get(['investment_id', 'date', 'price'])
            ->groupBy('investment_id');

        $pricedTransactionsByInvestment = Transaction::with('config')
            ->where('schedule', false)
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($investmentIds) {
                    $query->whereIn('investment_id', $investmentIds)
                        ->whereNotNull('price');
                }
            )
            ->orderBy('date')
            ->get()
            ->filter(function (Transaction $transaction) {
                $config = $transaction->config;

                return $config instanceof TransactionDetailInvestment;
            })
            ->groupBy(function (Transaction $transaction) {
                $config = $transaction->config;

                return $config instanceof TransactionDetailInvestment ? $config->investment_id : null;
            });

        $results = [];

        foreach ($requests as $request) {
            $investment = $request['investment'];
            $onOrBefore = $request['date'];
            $key = $this->priceBatchKey($investment->id, $onOrBefore);

            if (array_key_exists($key, $results)) {
                continue;
            }

            $price = $this->latestOnOrBefore($storedPricesByInvestment->get($investment->id), $onOrBefore);
            // Reuse the already-loaded $investment (including its currency, if the caller
            // eager-loaded it) so MoneyCast's price-currency resolution doesn't lazy-load
            // investment.currency per row - the dominant cost this batch method exists to avoid.
            $price?->setRelation('investment', $investment);

            $transaction = $this->latestOnOrBefore($pricedTransactionsByInvestment->get($investment->id), $onOrBefore);
            if ($transaction?->config instanceof TransactionDetailInvestment) {
                $transaction->config->setRelation('investment', $investment);
            }

            $results[$key] = $this->resolveCombinedPrice($price, $transaction);
        }

        return $results;
    }

    /**
     * From a date-ascending collection, pick the item with the highest date on or before
     * $onOrBefore (or the overall last item when no date bound is given).
     *
     * @template TItem of InvestmentPrice|Transaction
     * @param  Collection<int, TItem>|null  $items
     */
    private function latestOnOrBefore(?Collection $items, ?Carbon $onOrBefore): InvestmentPrice|Transaction|null
    {
        if ($items === null || $items->isEmpty()) {
            return null;
        }

        if ($onOrBefore === null) {
            return $items->last();
        }

        return $items->last(fn ($item) => $item->date <= $onOrBefore);
    }

    private function extractTransactionPrice(?Transaction $transaction): ?BigDecimal
    {
        if (! $transaction instanceof Transaction) {
            return null;
        }

        $transactionConfig = $transaction->config;

        if (! $transactionConfig instanceof TransactionDetailInvestment) {
            return null;
        }

        return $transactionConfig->price->getAmount();
    }

    /**
     * Get price history for an investment, ordered by date. `investment_id` and the
     * eager-loaded `investment` relation are hidden again before returning - both are only
     * loaded so MoneyCast can resolve price's currency via investment.currency, and aren't
     * part of this endpoint's response shape.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, InvestmentPrice>
     */
    public function getPrices(Investment $investment, array $columns = ['*']): Collection
    {
        return InvestmentPrice::where('investment_id', $investment->id)
            ->select($columns)
            ->with('investment.currency')
            ->orderBy('date')
            ->get()
            ->makeHidden(['investment_id', 'investment']);
    }

    /**
     * Save price without triggering observers
     * Private helper for fetchAndSavePrices
     *
     * @param  Investment  $investment  The investment
     * @param  string  $date  The date in Y-m-d format
     * @param  float  $price  The price value
     */
    private function savePriceQuietly(Investment $investment, string $date, float $price): void
    {
        $investmentPrice = InvestmentPrice::firstOrNew([
            'investment_id' => $investment->id,
            'date' => $date,
        ]);
        $investmentPrice->price = $price;

        // We are intentionally not triggering the observer here, as there can be multiple similar operations
        // It means, that it's the responsibility of the caller to trigger the observer or any related actions
        $investmentPrice->saveQuietly();
    }
}
