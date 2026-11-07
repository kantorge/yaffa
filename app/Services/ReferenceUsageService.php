<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use DateTimeInterface;

/**
 * Recent-usage aggregates behind "most used / recently used" suggestions in transaction entry (web and mobile).
 *
 * Everything is limited to a rolling window, so the payload stays bounded and favours current habits. Clients
 * derive per-entity rankings by summing the rows, and keep them current locally after saving a transaction.
 */
class ReferenceUsageService
{
    public const WINDOW_MONTHS = 12;

    /** Categories kept per payee. */
    public const CATEGORIES_PER_PAYEE = 5;

    /**
     * @return array{
     *     window_months: int,
     *     standard: list<array{type: string, account_from_id: int, account_to_id: int, uses: int, last_used: string}>,
     *     investment: list<array{type: string, account_id: int, investment_id: int, uses: int, last_used: string}>,
     *     payee_categories: list<array{payee_id: int, category_id: int, uses: int}>,
     *     deferred_categories: list<array{payee_id: int, category_id: int}>
     * }
     */
    public function forUser(User $user): array
    {
        $since = now()->subMonthsNoOverflow(self::WINDOW_MONTHS)->startOfDay();

        return [
            'window_months' => self::WINDOW_MONTHS,
            'standard' => $this->standardPairs($user, $since),
            'investment' => $this->investmentPairs($user, $since),
            'payee_categories' => $this->payeeCategories($user, $since),
            'deferred_categories' => $this->deferredCategories($user),
        ];
    }

    /**
     * @return list<array{type: string, account_from_id: int, account_to_id: int, uses: int, last_used: string}>
     */
    private function standardPairs(User $user, DateTimeInterface $since): array
    {
        return DB::table('transactions')
            ->join('transaction_details_standard as d', 'd.id', '=', 'transactions.config_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', false)
            ->where('transactions.date', '>=', $since)
            ->groupBy('transactions.transaction_type', 'd.account_from_id', 'd.account_to_id')
            ->selectRaw('transactions.transaction_type as type, d.account_from_id, d.account_to_id, count(*) as uses, max(transactions.date) as last_used')
            ->get()
            ->map(fn ($r) => [
                'type' => (string) $r->type,
                'account_from_id' => (int) $r->account_from_id,
                'account_to_id' => (int) $r->account_to_id,
                'uses' => (int) $r->uses,
                'last_used' => mb_substr((string) $r->last_used, 0, 10),
            ])->all();
    }

    /**
     * @return list<array{type: string, account_id: int, investment_id: int, uses: int, last_used: string}>
     */
    private function investmentPairs(User $user, DateTimeInterface $since): array
    {
        return DB::table('transactions')
            ->join('transaction_details_investment as d', 'd.id', '=', 'transactions.config_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.config_type', 'investment')
            ->where('transactions.schedule', false)
            ->where('transactions.date', '>=', $since)
            ->groupBy('transactions.transaction_type', 'd.account_id', 'd.investment_id')
            ->selectRaw('transactions.transaction_type as type, d.account_id, d.investment_id, count(*) as uses, max(transactions.date) as last_used')
            ->get()
            ->map(fn ($r) => [
                'type' => (string) $r->type,
                'account_id' => (int) $r->account_id,
                'investment_id' => (int) $r->investment_id,
                'uses' => (int) $r->uses,
                'last_used' => mb_substr((string) $r->last_used, 0, 10),
            ])->all();
    }

    /**
     * Top categories per payee, counted over transaction items. A payee can be on either side of a transaction.
     *
     * @return list<array{payee_id: int, category_id: int, uses: int}>
     */
    private function payeeCategories(User $user, DateTimeInterface $since): array
    {
        $directional = fn (string $column) => DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('transaction_details_standard as d', 'd.id', '=', 'transactions.config_id')
            ->join('account_entities as payee', 'payee.id', '=', "d.{$column}")
            ->where('transactions.user_id', $user->id)
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', false)
            ->where('transactions.date', '>=', $since)
            ->where('payee.config_type', 'payee')
            ->whereNotNull('transaction_items.category_id')
            ->selectRaw("d.{$column} as payee_id, transaction_items.category_id");

        $rows = DB::query()
            ->fromSub($directional('account_to_id')->unionAll($directional('account_from_id')), 'base')
            ->select(['payee_id', 'category_id'])
            ->selectRaw('count(*) as uses')
            ->groupBy('payee_id', 'category_id')
            ->orderByDesc('uses')
            ->get();

        return $rows->groupBy('payee_id')
            ->flatMap(fn ($group) => $group->take(self::CATEGORIES_PER_PAYEE))
            ->map(fn ($r) => [
                'payee_id' => (int) $r->payee_id,
                'category_id' => (int) $r->category_id,
                'uses' => (int) $r->uses,
            ])->values()->all();
    }

    /**
     * @return list<array{payee_id: int, category_id: int}>
     */
    private function deferredCategories(User $user): array
    {
        return DB::table('account_entity_category_preference as p')
            ->join('account_entities as e', 'e.id', '=', 'p.account_entity_id')
            ->where('e.user_id', $user->id)
            ->where('p.preferred', false)
            ->get(['p.account_entity_id', 'p.category_id'])
            ->map(fn ($r) => [
                'payee_id' => (int) $r->account_entity_id,
                'category_id' => (int) $r->category_id,
            ])->all();
    }
}
