<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayeeCategoryStatsService
{
    /**
     * Get category usage stats for one payee.
     *
     * @return Collection<int, array{category_id: int, usage_count: int}>
     */
    public function getCategoryStatsForPayee(User $user, AccountEntity $payee, int $months = 6): Collection
    {
        if (! $payee->isPayee() || $payee->user_id !== $user->id) {
            return collect();
        }

        return $this->buildAggregatedStatsQuery($user, $months, $payee->id)
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->category_id,
                'usage_count' => (int) $row->usage_count,
            ])
            ->values();
    }

    /**
     * Get category usage stats for all payees of the user.
     *
     * @return Collection<int, object{payee_id: int, category_id: int, usage_count: int}>
     */
    public function getCategoryStatsForAllPayees(User $user, ?int $months = null): Collection
    {
        return $this->buildAggregatedStatsQuery($user, $months)
            ->get()
            ->map(function ($row) {
                $row->payee_id = (int) $row->payee_id;
                $row->category_id = (int) $row->category_id;
                $row->usage_count = (int) $row->usage_count;

                return $row;
            })
            ->values();
    }

    /**
     * Get default category suggestions for all eligible payees.
     *
     * @return Collection<int, array{payee_id: int, sum: int, max: int, max_category_id: int, payee: string, category: string}>
     */
    public function getDefaultSuggestionsForAllPayees(User $user, ?int $months = null): Collection
    {
        $payees = $this->getEligibleDefaultSuggestions(
            user: $user,
            months: $months,
            onlyActive: false,
            excludeDismissed: false,
        );

        if ($payees->isEmpty()) {
            return collect();
        }

        $payeeNames = AccountEntity::query()
            ->whereIn('id', $payees->pluck('payee_id')->all())
            ->where('user_id', $user->id)
            ->where('config_type', 'payee')
            ->pluck('name', 'id');

        $categoryNames = Category::query()
            ->with('parent')
            ->whereIn('id', $payees->pluck('max_category_id')->all())
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->full_name]);

        return $payees
            ->map(function (array $payee) use ($payeeNames, $categoryNames): ?array {
                $payeeName = $payeeNames->get($payee['payee_id']);
                $categoryName = $categoryNames->get($payee['max_category_id']);

                if ($payeeName === null || $categoryName === null) {
                    return null;
                }

                $payee['payee'] = (string) $payeeName;
                $payee['category'] = (string) $categoryName;

                return $payee;
            })
            ->filter()
            ->values();
    }

    /**
     * Get suggestion data for a payee default category recommendation.
     *
     * @return array{payee_id: int, sum: int, max: int, max_category_id: int, payee: string, category: string}|null
     */
    public function getDefaultSuggestion(User $user): ?array
    {
        $payees = $this->getEligibleDefaultSuggestions(
            user: $user,
            months: null,
            onlyActive: true,
            excludeDismissed: true,
        );

        if ($payees->isEmpty()) {
            return null;
        }

        $payee = $this->getDefaultSuggestionsForAllPayees($user)
            ->keyBy('payee_id')
            ->only($payees->pluck('payee_id')->all())
            ->values();

        if ($payee->isEmpty()) {
            return null;
        }

        return $payee->random();
    }

    /**
     * @return Collection<int, array{payee_id: int, sum: int, max: int, max_category_id: int}>
     */
    private function getEligibleDefaultSuggestions(
        User $user,
        ?int $months = null,
        bool $onlyActive = true,
        bool $excludeDismissed = true,
    ): Collection {
        $data = $this->getCategoryStatsForAllPayees($user, $months);

        $eligiblePayeeIds = DB::table('account_entities')
            ->join('payees', 'payees.id', '=', 'account_entities.config_id')
            ->where('account_entities.user_id', $user->id)
            ->where('account_entities.config_type', 'payee')
            ->when($onlyActive, fn ($query) => $query->where('account_entities.active', true))
            ->whereNull('payees.category_id')
            ->when($excludeDismissed, fn ($query) => $query->whereNull('payees.category_suggestion_dismissed'))
            ->pluck('account_entities.id')
            ->all();

        return $data
            ->groupBy('payee_id')
            ->map(function (Collection $payeeStats): ?array {
                /** @var object{payee_id: int, category_id: int, usage_count: int}|null $maxItem */
                $maxItem = $payeeStats->sortByDesc('usage_count')->first();
                if ($maxItem === null) {
                    return null;
                }

                /** @var object{payee_id: int} $firstItem */
                $firstItem = $payeeStats->first();

                return [
                    'payee_id' => (int) $firstItem->payee_id,
                    'sum' => (int) $payeeStats->sum('usage_count'),
                    'max' => (int) $maxItem->usage_count,
                    'max_category_id' => (int) $maxItem->category_id,
                ];
            })
            ->filter()
            ->filter(fn (array $value) => $value['sum'] > 5)
            ->filter(fn (array $value) => $value['max'] / $value['sum'] > 0.5)
            ->filter(fn (array $value) => in_array($value['payee_id'], $eligiblePayeeIds, true))
            ->map(fn (array $value): array => [
                'payee_id' => (int) $value['payee_id'],
                'sum' => (int) $value['sum'],
                'max' => (int) $value['max'],
                'max_category_id' => (int) $value['max_category_id'],
            ])
            ->values();
    }

    private function buildAggregatedStatsQuery(User $user, ?int $months = null, ?int $payeeId = null)
    {
        $toQuery = $this->buildDirectionalBaseQuery($user, 'account_to_id', $months, $payeeId);
        $fromQuery = $this->buildDirectionalBaseQuery($user, 'account_from_id', $months, $payeeId);

        $baseQuery = $toQuery->unionAll($fromQuery);

        return DB::query()
            ->fromSub($baseQuery, 'base')
            ->select(['payee_id', 'category_id'])
            ->selectRaw('count(*) as usage_count')
            ->groupBy(['payee_id', 'category_id'])
            ->orderByDesc('usage_count');
    }

    private function buildDirectionalBaseQuery(User $user, string $payeeColumn, ?int $months = null, ?int $payeeId = null)
    {
        $query = DB::table('transaction_items')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->join(
                'transaction_details_standard',
                'transaction_details_standard.id',
                '=',
                'transactions.config_id'
            )
            ->join(
                'account_entities as payee_entities',
                'payee_entities.id',
                '=',
                "transaction_details_standard.{$payeeColumn}"
            )
            ->join(
                'categories',
                'categories.id',
                '=',
                'transaction_items.category_id'
            )
            ->where('transactions.user_id', $user->id)
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', false)
            ->where('transactions.budget', false)
            ->where('payee_entities.user_id', $user->id)
            ->where('payee_entities.config_type', 'payee')
            ->where('categories.user_id', $user->id)
            ->where('categories.active', true)
            ->whereNotNull('transaction_items.category_id')
            ->selectRaw("transaction_details_standard.{$payeeColumn} as payee_id")
            ->addSelect('transaction_items.category_id');

        if ($months !== null) {
            $query->where('transactions.date', '>=', now()->subMonths($months)->startOfDay());
        }

        if ($payeeId !== null) {
            $query->where("transaction_details_standard.{$payeeColumn}", $payeeId);
        }

        return $query;
    }
}
