<?php

namespace App\Services;

use App\Enums\CategoryTagRequirement;
use App\Enums\TransactionType;
use App\Http\Traits\CurrencyTrait;
use App\Models\Tag;
use App\Models\TransactionItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recharges: purchases made on behalf of someone else, booked to a category that expects a tag
 * (e.g. a claim number). Lists items that miss their tag, and the balance of each tag used in an
 * "expect nil" category, so open claims can be followed up and settled ones deactivated.
 *
 * Recharge tags are opt-in per user (`users.recharge_tags_enabled`). The user's backstop date
 * (`users.recharge_backstop_date`) hides older activity: untagged items dated before it, and tags
 * whose latest item is before it. A tag still active after the backstop keeps its full balance,
 * including items before the date, so a claim that spans the date is not shown as open.
 *
 * Always call it in the request context of the owning user (CurrencyTrait reads auth()).
 */
class RechargeTagService
{
    use CurrencyTrait;

    /** A tag whose balance is within this amount (base currency) of zero is settled. */
    public const string SETTLED_TOLERANCE = '0.01';

    private const int SCALE = 4;

    public function isEnabled(User $user): bool
    {
        return (bool) $user->recharge_tags_enabled;
    }

    public function hasTagRequiredCategories(User $user): bool
    {
        return $user->categories()
            ->where('tag_requirement', '!=', CategoryTagRequirement::NONE->value)
            ->exists();
    }

    /**
     * Items of real (non-schedule) transactions in a category that expects a tag, without any tag.
     *
     * @return EloquentCollection<int, TransactionItem>
     */
    public function untaggedItems(User $user): EloquentCollection
    {
        return $this->untaggedItemsQuery($user)
            ->with([
                'category.parent',
                'transaction.config.accountFrom',
                'transaction.config.accountTo',
                'transaction.currency',
            ])
            ->get()
            ->sortByDesc(fn (TransactionItem $item) => $item->transaction->date)
            ->values();
    }

    public function untaggedItemCount(User $user): int
    {
        return $this->untaggedItemsQuery($user)->count();
    }

    /**
     * Every tag used on at least one item in an "expect nil" category, with its balance in the base
     * currency: the sum of all standard items carrying the tag (in any category), deposits counted
     * positive and withdrawals negative.
     *
     * @return Collection<int, array{id: int, name: string, active: bool, balance: string, item_count: int, first_date: string|null, last_date: string|null, rates_missing: bool, settled: bool}>
     */
    public function tagBalances(User $user): Collection
    {
        $tagIds = DB::table('transaction_items_tags as it')
            ->join('transaction_items as i', 'i.id', '=', 'it.transaction_item_id')
            ->join('transactions', 'transactions.id', '=', 'i.transaction_id')
            ->join('categories', 'categories.id', '=', 'i.category_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.schedule', false)
            ->where('categories.tag_requirement', CategoryTagRequirement::REQUIRED_NIL->value)
            ->distinct()
            ->pluck('it.tag_id');

        if ($tagIds->isEmpty()) {
            return collect();
        }

        $rows = DB::table('transaction_items_tags as it')
            ->join('transaction_items as i', 'i.id', '=', 'it.transaction_item_id')
            ->join('transactions', 'transactions.id', '=', 'i.transaction_id')
            ->whereIn('it.tag_id', $tagIds)
            ->where('transactions.user_id', $user->id)
            ->where('transactions.schedule', false)
            ->where('transactions.config_type', 'standard')
            ->whereIn('transactions.transaction_type', [
                TransactionType::WITHDRAWAL->value,
                TransactionType::DEPOSIT->value,
            ])
            ->groupBy(
                'it.tag_id',
                'transactions.currency_id',
                'transactions.transaction_type',
                DB::raw("DATE_FORMAT(transactions.date, '%Y-%m-01')"),
            )
            ->selectRaw(
                "it.tag_id, transactions.currency_id, transactions.transaction_type,"
                . " DATE_FORMAT(transactions.date, '%Y-%m-01') as month, COUNT(*) as cnt,"
                . ' MIN(transactions.date) as first_date, MAX(transactions.date) as last_date, SUM(i.amount) as total'
            )
            ->get()
            ->groupBy('tag_id');

        $baseCurrency = $this->getBaseCurrency($user->id);
        // CurrencyTrait resolves the user through auth(); the pages are only reachable by the owner.
        $ratesMap = $baseCurrency && auth()->id() === $user->id ? $this->allCurrencyRatesByMonth() : [];
        $tolerance = BigDecimal::of(self::SETTLED_TOLERANCE);
        $backstop = $user->recharge_backstop_date?->format('Y-m-d');

        return Tag::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $tagIds)
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(function (Tag $tag) use ($rows, $baseCurrency, $ratesMap, $tolerance): array {
                $tagRows = $rows->get($tag->id, collect());
                $balance = BigDecimal::zero();
                $ratesMissing = false;

                foreach ($tagRows as $row) {
                    // A transaction without a currency is in the base currency
                    $currencyId = (int) ($row->currency_id ?? $baseCurrency?->id);
                    $rate = $baseCurrency
                        ? $this->getLatestRateFromMap($currencyId, Carbon::parse($row->month), $ratesMap, $baseCurrency->id)
                        : null;
                    // No rate for a foreign currency: the amount is added unconverted, so flag the balance
                    if ($baseCurrency && $rate === null && $currencyId !== $baseCurrency->id) {
                        $ratesMissing = true;
                    }

                    $multiplier = TransactionType::from($row->transaction_type)->amountMultiplier();
                    $balance = $balance->plus(
                        BigDecimal::of($row->total)->multipliedBy($rate ?? '1')->multipliedBy($multiplier)
                    );
                }

                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'active' => (bool) $tag->active,
                    'balance' => (string) $balance->toScale(self::SCALE, RoundingMode::HalfUp),
                    'item_count' => (int) $tagRows->sum('cnt'),
                    'first_date' => $this->dateString($tagRows->min('first_date')),
                    'last_date' => $this->dateString($tagRows->max('last_date')),
                    'rates_missing' => $ratesMissing,
                    // An amount added unconverted (missing rate) could cancel out by accident: never settled
                    'settled' => ! $ratesMissing && $balance->abs()->isLessThanOrEqualTo($tolerance),
                ];
            })
            // Hide tags with no activity on or after the backstop date
            ->filter(fn (array $row) => $backstop === null || ($row['last_date'] !== null && $row['last_date'] >= $backstop))
            ->values();
    }

    /**
     * Deactivate the given tags, but only those that are currently settled and active.
     * Without ids, every settled active tag is deactivated.
     *
     * @param  array<int>|null  $tagIds
     * @return int The number of tags deactivated
     */
    public function deactivateSettled(User $user, ?array $tagIds = null): int
    {
        $settledIds = $this->tagBalances($user)
            ->filter(fn (array $row) => $row['settled'] && $row['active'])
            ->pluck('id')
            ->when($tagIds !== null, fn (Collection $ids) => $ids->intersect(array_map('intval', $tagIds)))
            ->values();

        if ($settledIds->isEmpty()) {
            return 0;
        }

        return Tag::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $settledIds)
            ->update(['active' => false]);
    }

    /**
     * @return array{untagged_items: int, open_tags: int, settled_active_tags: int}
     */
    public function summary(User $user): array
    {
        $balances = $this->tagBalances($user);

        return [
            'untagged_items' => $this->untaggedItemCount($user),
            'open_tags' => $balances->where('settled', false)->count(),
            'settled_active_tags' => $balances->filter(fn (array $row) => $row['settled'] && $row['active'])->count(),
        ];
    }

    /**
     * A DATE column read through the query builder, as Y-m-d (or null when there are no rows).
     */
    private function dateString(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * @return Builder<TransactionItem>
     */
    private function untaggedItemsQuery(User $user): Builder
    {
        $backstop = $user->recharge_backstop_date;

        return TransactionItem::query()
            ->whereHas('transaction', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('schedule', false)
                ->when($backstop !== null, fn (Builder $query) => $query->whereDate('date', '>=', $backstop)))
            ->whereHas('category', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('tag_requirement', '!=', CategoryTagRequirement::NONE->value))
            ->whereDoesntHave('tags');
    }
}
