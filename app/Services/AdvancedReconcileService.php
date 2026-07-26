<?php

namespace App\Services;

use App\Enums\CheckpointType;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountBalanceCheckpoint;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AdvancedReconcileService
{
    public function __construct(
        private readonly InvestmentService $investmentService,
    ) {
    }

    /**
     * Build the reconciliation payload for one account and period.
     *
     * @return array<string, mixed>
     */
    public function accountSummary(AccountEntity $accountEntity, Carbon $dateFrom, Carbon $dateTo): array
    {
        $accountEntity->loadMissing(['config', 'config.currency']);

        $cash = $this->cashSection($accountEntity, $dateFrom, $dateTo);
        $investments = $this->investmentSection($accountEntity, $dateFrom, $dateTo);
        $total = $this->totalSection($accountEntity, $dateTo, $cash, $investments);

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'currency' => $accountEntity->config instanceof Account ? $accountEntity->config->currency : null,
            'cash' => $cash,
            'investment' => $investments,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(AccountEntity $accountEntity, Carbon $month, CheckpointType $checkpointType): array
    {
        $checkpoint = AccountBalanceCheckpoint::where('account_entity_id', $accountEntity->id)
            ->where('checkpoint_type', $checkpointType->value)
            ->where('active', true)
            ->whereBetween('checkpoint_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->latest('checkpoint_date')
            ->latest('id')
            ->first();

        if ($checkpoint === null) {
            return [
                'status' => 'no_checkpoint',
                'checkpoint' => null,
                'calculated_balance' => null,
                'variance' => null,
                'date_from' => $month->copy()->startOfMonth()->toDateString(),
                'date_to' => $month->copy()->endOfMonth()->toDateString(),
            ];
        }

        $previousCheckpointDate = AccountBalanceCheckpoint::where('account_entity_id', $accountEntity->id)
            ->where('checkpoint_type', $checkpointType->value)
            ->where('active', true)
            ->where('checkpoint_date', '<', $checkpoint->checkpoint_date)
            ->latest('checkpoint_date')
            ->value('checkpoint_date');

        $dateFrom = $previousCheckpointDate === null
            ? $checkpoint->checkpoint_date->copy()->startOfMonth()
            : Carbon::parse($previousCheckpointDate)->addDay();

        $calculatedBalance = $this->calculatedBalanceAt($accountEntity, $checkpoint->checkpoint_date, $checkpointType);
        $variance = round($checkpoint->balance - $calculatedBalance, 2);

        return [
            'status' => abs($variance) < 0.01 ? 'matched' : 'reconcile_required',
            'checkpoint' => $checkpoint,
            'calculated_balance' => $calculatedBalance,
            'variance' => $variance,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $checkpoint->checkpoint_date->toDateString(),
        ];
    }

    public function calculatedBalanceAt(AccountEntity $accountEntity, Carbon $date, CheckpointType $checkpointType): float
    {
        $cashBalance = $this->cashBalanceAt($accountEntity, $date);

        if ($checkpointType === CheckpointType::CASH) {
            return $cashBalance;
        }

        $investmentBalance = $this->investmentValueAt($accountEntity, $date)['value'];

        if ($checkpointType === CheckpointType::INVESTMENT) {
            return $investmentBalance;
        }

        return round($cashBalance + $investmentBalance, 2);
    }

    /**
     * @param array{checkpoint_date: string, checkpoint_type: string, balance: numeric, note?: string|null, source?: string|null, source_document_id?: string|null} $data
     */
    public function storeCheckpoint(User $user, AccountEntity $accountEntity, array $data): AccountBalanceCheckpoint
    {
        return AccountBalanceCheckpoint::create([
            'user_id' => $user->id,
            'account_entity_id' => $accountEntity->id,
            'checkpoint_date' => $data['checkpoint_date'],
            'checkpoint_type' => CheckpointType::from($data['checkpoint_type'])->value,
            'balance' => $data['balance'],
            'note' => $data['note'] ?? null,
            'active' => true,
            'source' => $data['source'] ?? 'manual',
            'source_document_id' => $data['source_document_id'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cashSection(AccountEntity $accountEntity, Carbon $dateFrom, Carbon $dateTo): array
    {
        $openingBalance = $this->cashBalanceBefore($accountEntity, $dateFrom);
        $movements = $this->cashMovements($accountEntity, $dateFrom, $dateTo);

        $totalDeposits = round($movements->filter(fn (float $amount): bool => $amount > 0)->sum(), 2);
        $totalWithdrawals = round(abs($movements->filter(fn (float $amount): bool => $amount < 0)->sum()), 2);
        $balance = round($openingBalance + $totalDeposits - $totalWithdrawals, 2);
        $checkpoint = $this->checkpointForDate($accountEntity, $dateTo, CheckpointType::CASH);

        return $this->withCheckpointState([
            'opening_balance' => $openingBalance,
            'total_withdrawals' => $totalWithdrawals,
            'total_deposits' => $totalDeposits,
            'balance' => $balance,
        ], $checkpoint, $balance);
    }

    /**
     * @return array<string, mixed>
     */
    private function investmentSection(AccountEntity $accountEntity, Carbon $dateFrom, Carbon $dateTo): array
    {
        $opening = $this->investmentValueAt($accountEntity, $dateFrom->copy()->subDay());
        $closing = $this->investmentValueAt($accountEntity, $dateTo);
        $holdings = $this->investmentHoldings($accountEntity, $dateFrom, $dateTo);
        $checkpoint = $this->checkpointForDate($accountEntity, $dateTo, CheckpointType::INVESTMENT);

        return $this->withCheckpointState([
            'opening_value' => $opening['value'],
            'closing_value' => $closing['value'],
            'balance' => $closing['value'],
            'holdings' => $holdings,
            'missing_price_count' => collect($holdings)->where('has_missing_price', true)->count(),
        ], $checkpoint, $closing['value']);
    }

    /**
     * @param array<string, mixed> $cash
     * @param array<string, mixed> $investments
     * @return array<string, mixed>
     */
    private function totalSection(AccountEntity $accountEntity, Carbon $dateTo, array $cash, array $investments): array
    {
        $balance = round($cash['balance'] + $investments['balance'], 2);
        $checkpoint = $this->checkpointForDate($accountEntity, $dateTo, CheckpointType::TOTAL);

        return $this->withCheckpointState([
            'balance' => $balance,
        ], $checkpoint, $balance);
    }

    private function cashBalanceBefore(AccountEntity $accountEntity, Carbon $date): float
    {
        return $this->cashBalanceAt($accountEntity, $date->copy()->subDay());
    }

    private function cashBalanceAt(AccountEntity $accountEntity, Carbon $date): float
    {
        $accountEntity->loadMissing('config');

        $openingBalance = $accountEntity->config instanceof Account
            ? (float) $accountEntity->config->opening_balance
            : 0.0;

        return round($openingBalance + $this->cashMovements($accountEntity, null, $date)->sum(), 2);
    }

    /**
     * @return Collection<int, float>
     */
    private function cashMovements(AccountEntity $accountEntity, ?Carbon $dateFrom, Carbon $dateTo): Collection
    {
        $standardFrom = TransactionDetailStandard::query()
            ->join('transactions', 'transaction_details_standard.id', '=', 'transactions.config_id')
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', 0)
            ->where('transactions.budget', 0)
            ->where('transaction_details_standard.account_from_id', $accountEntity->id)
            ->when($dateFrom, fn ($query) => $query->where('transactions.date', '>=', $dateFrom->toDateString()))
            ->where('transactions.date', '<=', $dateTo->toDateString())
            ->pluck('transaction_details_standard.amount_from')
            ->map(fn ($amount): float => -1 * (float) $amount);

        $standardTo = TransactionDetailStandard::query()
            ->join('transactions', 'transaction_details_standard.id', '=', 'transactions.config_id')
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', 0)
            ->where('transactions.budget', 0)
            ->where('transaction_details_standard.account_to_id', $accountEntity->id)
            ->when($dateFrom, fn ($query) => $query->where('transactions.date', '>=', $dateFrom->toDateString()))
            ->where('transactions.date', '<=', $dateTo->toDateString())
            ->pluck('transaction_details_standard.amount_to')
            ->map(fn ($amount): float => (float) $amount);

        $investment = TransactionDetailInvestment::query()
            ->join('transactions', 'transaction_details_investment.id', '=', 'transactions.config_id')
            ->where('transactions.config_type', 'investment')
            ->where('transactions.schedule', 0)
            ->where('transactions.budget', 0)
            ->where('transaction_details_investment.account_id', $accountEntity->id)
            ->when($dateFrom, fn ($query) => $query->where('transactions.date', '>=', $dateFrom->toDateString()))
            ->where('transactions.date', '<=', $dateTo->toDateString())
            ->pluck('transactions.cashflow_value')
            ->map(fn ($amount): float => (float) $amount);

        return $standardFrom->concat($standardTo)->concat($investment)->values();
    }

    /**
     * @return array{value: float, missing_price_count: int}
     */
    private function investmentValueAt(AccountEntity $accountEntity, Carbon $date): array
    {
        $quantities = $this->quantitiesAt($accountEntity, $date);
        $investments = Investment::whereIn('id', $quantities->pluck('investment_id'))->get()->keyBy('id');
        $missingPriceCount = 0;

        $value = $quantities->sum(function (object $item) use ($date, $investments, &$missingPriceCount): float {
            $quantity = (float) $item->quantity;
            if ($quantity === 0.0) {
                return 0.0;
            }

            $investment = $investments->get($item->investment_id);
            if ($investment === null) {
                return 0.0;
            }

            $price = $this->investmentService->getLatestPrice($investment, 'combined', $date);
            if ($price === null) {
                $missingPriceCount++;
                $price = 0.0;
            }

            return $quantity * $price;
        });

        return [
            'value' => round($value, 2),
            'missing_price_count' => $missingPriceCount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function investmentHoldings(AccountEntity $accountEntity, Carbon $dateFrom, Carbon $dateTo): array
    {
        $openingQuantities = $this->quantitiesAt($accountEntity, $dateFrom->copy()->subDay())->keyBy('investment_id');
        $closingQuantities = $this->quantitiesAt($accountEntity, $dateTo)->keyBy('investment_id');
        $periodQuantityChanges = $this->periodInvestmentQuantityChanges($accountEntity, $dateFrom, $dateTo)->keyBy('investment_id');

        $investmentIds = $openingQuantities
            ->keys()
            ->merge($closingQuantities->keys())
            ->merge($periodQuantityChanges->keys())
            ->unique();
        $investments = Investment::whereIn('id', $investmentIds)->get()->keyBy('id');

        return $investmentIds
            ->map(function (int $investmentId) use ($investments, $openingQuantities, $closingQuantities, $periodQuantityChanges, $dateFrom, $dateTo): ?array {
                $investment = $investments->get($investmentId);
                if ($investment === null) {
                    return null;
                }

                $openQuantity = (float) ($openingQuantities->get($investmentId)->quantity ?? 0);
                $closeQuantity = (float) ($closingQuantities->get($investmentId)->quantity ?? 0);
                $periodChanges = $periodQuantityChanges->get($investmentId);
                $buys = (float) ($periodChanges->buys ?? 0);
                $sells = (float) ($periodChanges->sells ?? 0);

                if ($openQuantity === 0.0 && $closeQuantity === 0.0 && $buys === 0.0 && $sells === 0.0) {
                    return null;
                }

                $openPrice = $this->investmentService->getLatestPrice($investment, 'combined', $dateFrom);
                $closePrice = $this->investmentService->getLatestPrice($investment, 'combined', $dateTo);
                $openStoredPrice = $this->storedPriceForDate($investment, $dateFrom);
                $closeStoredPrice = $this->storedPriceForDate($investment, $dateTo);

                return [
                    'investment_id' => $investment->id,
                    'name' => $investment->name,
                    'symbol' => $investment->symbol,
                    'open_quantity' => $openQuantity,
                    'close_quantity' => $closeQuantity,
                    'buys' => $buys,
                    'sells' => $sells,
                    'open_price' => $openPrice,
                    'close_price' => $closePrice,
                    'open_price_date' => $dateFrom->toDateString(),
                    'close_price_date' => $dateTo->toDateString(),
                    'open_stored_price_id' => $openStoredPrice?->id,
                    'close_stored_price_id' => $closeStoredPrice?->id,
                    'open_value' => round($openQuantity * ($openPrice ?? 0), 2),
                    'close_value' => round($closeQuantity * ($closePrice ?? 0), 2),
                    'has_missing_price' => (($openQuantity !== 0.0 && $openPrice === null)
                        || ($closeQuantity !== 0.0 && $closePrice === null)),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function storedPriceForDate(Investment $investment, Carbon $date): ?InvestmentPrice
    {
        return InvestmentPrice::where('investment_id', $investment->id)
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * @return Collection<int, TransactionDetailInvestment>
     */
    private function quantitiesAt(AccountEntity $accountEntity, Carbon $date): Collection
    {
        return TransactionDetailInvestment::query()
            ->select(
                'transaction_details_investment.investment_id',
            )
            ->selectRaw(
                'SUM('
                . TransactionTypeEnum::getQuantityMultiplierSqlCase('transactions.transaction_type')
                . ' * IFNULL(transaction_details_investment.quantity, 0)) AS quantity'
            )
            ->join('transactions', 'transaction_details_investment.id', '=', 'transactions.config_id')
            ->where('transactions.schedule', 0)
            ->where('transactions.budget', 0)
            ->where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type', TransactionTypeEnum::investmentTypesWithQuantityValues())
            ->where('transaction_details_investment.account_id', $accountEntity->id)
            ->where('transactions.date', '<=', $date->toDateString())
            ->groupBy('transaction_details_investment.investment_id')
            ->get();
    }

    /**
     * @return Collection<int, TransactionDetailInvestment>
     */
    private function periodInvestmentQuantityChanges(AccountEntity $accountEntity, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        return TransactionDetailInvestment::query()
            ->select('transaction_details_investment.investment_id')
            ->selectRaw("SUM(CASE WHEN transactions.transaction_type IN ('buy', 'add_shares') THEN IFNULL(transaction_details_investment.quantity, 0) ELSE 0 END) AS buys")
            ->selectRaw("SUM(CASE WHEN transactions.transaction_type IN ('sell', 'remove_shares') THEN IFNULL(transaction_details_investment.quantity, 0) ELSE 0 END) AS sells")
            ->join('transactions', 'transaction_details_investment.id', '=', 'transactions.config_id')
            ->where('transactions.schedule', 0)
            ->where('transactions.budget', 0)
            ->where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type', TransactionTypeEnum::investmentTypesWithQuantityValues())
            ->where('transaction_details_investment.account_id', $accountEntity->id)
            ->whereBetween('transactions.date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('transaction_details_investment.investment_id')
            ->get();
    }

    private function checkpointForDate(AccountEntity $accountEntity, Carbon $date, CheckpointType $type): ?AccountBalanceCheckpoint
    {
        return AccountBalanceCheckpoint::where('account_entity_id', $accountEntity->id)
            ->where('checkpoint_type', $type->value)
            ->where('checkpoint_date', $date->toDateString())
            ->where('active', true)
            ->latest('id')
            ->first();
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    private function withCheckpointState(array $section, ?AccountBalanceCheckpoint $checkpoint, float $calculatedBalance): array
    {
        $variance = $checkpoint === null ? null : round($checkpoint->balance - $calculatedBalance, 2);

        return array_merge($section, [
            'checkpoint' => $checkpoint,
            'checkpoint_value' => $checkpoint?->balance,
            'variance' => $variance,
            'status' => $checkpoint === null
                ? 'no_checkpoint'
                : (abs($variance) < 0.01 ? 'matched' : 'reconcile_required'),
        ]);
    }
}
