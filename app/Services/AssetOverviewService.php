<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Http\Traits\CurrencyTrait;
use App\Models\AccountEntity;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Support\Facades\DB;
use Recurr\Exception\InvalidArgument;
use Recurr\Exception\InvalidWeekday;

/**
 * Lifetime overview figures for the payee and category show pages.
 *
 * Base-currency amounts are not stored: like the transactions API, they are derived from the
 * user's monthly average currency rates. Each overview is one aggregate query grouped by
 * currency, month and transaction type (bounded by months x currencies), converted in PHP.
 * Always call it in the request context of the owning user (CurrencyTrait reads auth()).
 */
class AssetOverviewService
{
    use CurrencyTrait;

    private const int SCALE = 4;

    public function __construct(private readonly RecurrenceRuleService $recurrenceRuleService)
    {
    }

    /**
     * Payee totals are transaction-level: what was paid to (withdrawals) and received from
     * (deposits) the payee. Schedules are excluded.
     *
     * @return array{count: int, first_date: string|null, last_date: string|null, withdrawal_total: string, deposit_total: string}
     */
    public function payeeOverview(User $user, AccountEntity $payee): array
    {
        $query = DB::table('transactions')
            ->join('transaction_details_standard as d', function ($join): void {
                $join->on('d.id', '=', 'transactions.config_id')
                    ->where('transactions.config_type', 'standard');
            })
            ->where(function (Builder $query) use ($payee): void {
                $query->where(fn (Builder $q) => $q
                    ->where('transactions.transaction_type', TransactionType::WITHDRAWAL->value)
                    ->where('d.account_to_id', $payee->id))
                    ->orWhere(fn (Builder $q) => $q
                        ->where('transactions.transaction_type', TransactionType::DEPOSIT->value)
                        ->where('d.account_from_id', $payee->id));
            });

        return $this->aggregate(
            $user,
            $query,
            'CASE WHEN transactions.transaction_type = \'withdrawal\' THEN d.amount_to ELSE d.amount_from END',
            'transactions.id',
        );
    }

    /**
     * Category totals are item-level, over the category and its children. Schedules are excluded.
     *
     * @return array{count: int, first_date: string|null, last_date: string|null, withdrawal_total: string, deposit_total: string}
     */
    public function categoryOverview(User $user, Category $category): array
    {
        $categoryIds = $category->children()->pluck('id')->push($category->id)->all();

        $query = DB::table('transaction_items as i')
            ->join('transactions', 'transactions.id', '=', 'i.transaction_id')
            ->whereIn('i.category_id', $categoryIds)
            ->whereIn('transactions.transaction_type', [
                TransactionType::WITHDRAWAL->value,
                TransactionType::DEPOSIT->value,
            ]);

        return $this->aggregate($user, $query, 'i.amount', 'i.id');
    }

    /**
     * Budgets of the category itself, each with its next occurrence (on or after today).
     *
     * @return EloquentCollection<int, Budget>
     */
    public function categoryBudgets(Category $category): EloquentCollection
    {
        /** @var EloquentCollection<int, Budget> $budgets */
        $budgets = $category->budgets()->with('account')->get();

        return $budgets->each(
            fn (Budget $budget) => $budget->setAttribute('next_occurrence', $this->nextOccurrence($budget))
        );
    }

    private function nextOccurrence(Budget $budget): ?string
    {
        // getOccurrencesAfter() looks a short window past its anchor, so a future-dated budget
        // must be anchored on its own start date, not today.
        $anchor = SupportCarbon::today()->subDay()->max($budget->start_date->copy()->subDay());

        try {
            $recurrence = $this->recurrenceRuleService->getOccurrencesAfter(
                $budget->start_date,
                $budget->effectiveRrule(),
                $anchor,
            );
        } catch (InvalidArgument|InvalidWeekday|Exception) {
            return null;
        }

        return $recurrence->count() === 0 ? null : $recurrence->first()->getStart()->format('Y-m-d');
    }

    /**
     * @return array{count: int, first_date: string|null, last_date: string|null, withdrawal_total: string, deposit_total: string}
     */
    private function aggregate(User $user, Builder $query, string $amountSql, string $countColumn): array
    {
        $rows = $query
            ->where('transactions.user_id', $user->id)
            ->where('transactions.schedule', false)
            ->groupBy('transactions.currency_id', 'transactions.transaction_type', DB::raw('DATE_FORMAT(transactions.date, \'%Y-%m-01\')'))
            ->selectRaw(
                "transactions.currency_id, transactions.transaction_type, DATE_FORMAT(transactions.date, '%Y-%m-01') as month,"
                . " COUNT(DISTINCT {$countColumn}) as cnt, MIN(transactions.date) as first_date,"
                . " MAX(transactions.date) as last_date, SUM({$amountSql}) as total"
            )
            ->get();

        $baseCurrency = $this->getBaseCurrency($user->id);
        $ratesMap = $baseCurrency ? $this->ratesMapFor($user) : [];

        $totals = [
            TransactionType::WITHDRAWAL->value => BigDecimal::zero(),
            TransactionType::DEPOSIT->value => BigDecimal::zero(),
        ];
        foreach ($rows as $row) {
            $rate = $baseCurrency
                ? $this->getLatestRateFromMap((int) $row->currency_id, Carbon::parse($row->month), $ratesMap, $baseCurrency->id)
                : null;
            $totals[$row->transaction_type] = $totals[$row->transaction_type]
                ->plus(BigDecimal::of($row->total)->multipliedBy($rate ?? '1'));
        }

        return [
            'count' => (int) $rows->sum('cnt'),
            'first_date' => $rows->min('first_date'),
            'last_date' => $rows->max('last_date'),
            'withdrawal_total' => (string) $totals[TransactionType::WITHDRAWAL->value]
                ->toScale(self::SCALE, RoundingMode::HalfUp),
            'deposit_total' => (string) $totals[TransactionType::DEPOSIT->value]
                ->toScale(self::SCALE, RoundingMode::HalfUp),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function ratesMapFor(User $user): array
    {
        // CurrencyTrait resolves the user through auth(); the pages are only reachable by the owner.
        return auth()->id() === $user->id ? $this->allCurrencyRatesByMonth() : [];
    }
}
