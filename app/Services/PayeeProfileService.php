<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\PayeeProfile;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Builds the history profile of a payee (what the user usually buys there, in which category, for how
 * much) and tells which payees would qualify for auto-recording. Nothing here records anything.
 */
class PayeeProfileService
{
    /** The most recent transactions that make up a profile. */
    public const int WINDOW = 50;

    /** An account is "typical" when it covers at least this share of the window. */
    private const float TYPICAL_ACCOUNT_SHARE = 0.2;

    private const int MAX_KNOWN_AMOUNTS = 20;

    /** Smallest history the template and itemization checks look at; fewer transactions say too little. */
    private const int MIN_SAMPLE_FOR_HINTS = 5;

    private const float MULTI_ITEM_SHARE_FOR_MISMATCH = 0.3;

    private const float SINGLE_ITEM_SHARE_FOR_MISMATCH = 0.05;

    private const float TEMPLATE_AMOUNT_MODE_SHARE = 0.8;

    /** "Near" means the lower bound is this close below the minimum. */
    private const float NEAR_WILSON_MARGIN = 0.1;

    public function __construct(private readonly AiUserSettingsResolver $settingsResolver)
    {
    }

    /**
     * Lower bound of the Wilson score interval. z = 1.645 is a two-sided 90% interval.
     */
    public static function wilsonLowerBound(int $successes, int $n, float $z = 1.645): float
    {
        if ($n <= 0) {
            return 0.0;
        }

        $p = $successes / $n;
        $z2 = $z * $z;

        return max(0.0, ($p + $z2 / (2 * $n) - $z * sqrt($p * (1 - $p) / $n + $z2 / (4 * $n * $n))) / (1 + $z2 / $n));
    }

    public function calculate(AccountEntity $payee): PayeeProfile
    {
        $transactions = $this->windowTransactions($payee);
        $itemsByTransaction = $this->itemsByTransaction($transactions->pluck('id')->all());
        $n = $transactions->count();

        // Dominant category: the one most single-item transactions are in
        $dominantCategoryId = $transactions
            ->map(fn (object $t) => $itemsByTransaction->get($t->id, collect()))
            ->filter(fn (Collection $items) => $items->count() === 1 && $items->first()->category_id !== null)
            ->map(fn (Collection $items) => (int) $items->first()->category_id)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $inDominant = $dominantCategoryId === null ? 0 : $transactions
            ->filter(fn (object $t) => $itemsByTransaction->get($t->id, collect())
                ->contains(fn (object $item) => (int) $item->category_id === $dominantCategoryId))
            ->count();
        $singleItemDominant = $dominantCategoryId === null ? 0 : $transactions
            ->filter(function (object $t) use ($itemsByTransaction, $dominantCategoryId) {
                $items = $itemsByTransaction->get($t->id, collect());

                return $items->count() === 1 && (int) $items->first()->category_id === $dominantCategoryId;
            })
            ->count();
        $multiItem = $transactions
            ->filter(fn (object $t) => $itemsByTransaction->get($t->id, collect())->count() > 1)
            ->count();

        $amounts = $transactions->map(fn (object $t) => $this->amountOf($t))->sort(fn (BigDecimal $a, BigDecimal $b) => $a->compareTo($b))->values();
        $amountCounts = $amounts->map(fn (BigDecimal $amount) => (string) $amount)->countBy()->sortDesc();

        $typicalAccountIds = $transactions
            ->map(fn (object $t) => (int) ($t->transaction_type === 'withdrawal' ? $t->account_from_id : $t->account_to_id))
            ->countBy()
            ->filter(fn (int $count) => $n > 0 && $count / $n >= self::TYPICAL_ACCOUNT_SHARE)
            ->sortDesc()
            ->keys()
            ->values()
            ->all();

        return PayeeProfile::updateOrCreate(
            ['account_entity_id' => $payee->id],
            [
                'sample_size' => $n,
                'dominant_category_id' => $dominantCategoryId,
                'dominant_share' => $this->share($inDominant, $n),
                'single_item_dominant_count' => $singleItemDominant,
                'wilson_lower' => round(self::wilsonLowerBound($singleItemDominant, $n), 4),
                'amount_median' => $n === 0 ? null : (string) $this->median($amounts),
                'amount_min' => $n === 0 ? null : (string) $amounts->first(),
                'amount_max' => $n === 0 ? null : (string) $amounts->last(),
                'amount_mode_share' => $n === 0 ? null : $this->share($amountCounts->first(), $n),
                'known_amounts' => $amountCounts->keys()->take(self::MAX_KNOWN_AMOUNTS)->values()->all(),
                'typical_account_ids' => $typicalAccountIds,
                'multi_item_share' => $this->share($multiItem, $n),
                'calculated_at' => now(),
            ],
        );
    }

    /**
     * Whether a profile passes the history gate, and if not, how many more matching transactions it would take.
     *
     * @param  array<string, mixed>  $settings  Resolved AI settings
     * @return array{qualifies: bool, missing: ?int, reason: string}
     */
    public function qualification(PayeeProfile $profile, string $policy, array $settings): array
    {
        if ($policy === Payee::POLICY_NEVER) {
            return ['qualifies' => false, 'missing' => null, 'reason' => __('Auto-recording is set to never for this payee.')];
        }

        if ($policy === Payee::POLICY_ALWAYS) {
            return ['qualifies' => true, 'missing' => 0, 'reason' => __('Auto-recording is always allowed for this payee.')];
        }

        $minHistory = (int) $settings['auto_record_min_history'];
        $wilsonMin = (float) $settings['auto_record_wilson_min'];
        $wilson = (float) $profile->wilson_lower;

        if ($profile->sample_size >= $minHistory && $wilson >= $wilsonMin) {
            return [
                'qualifies' => true,
                'missing' => 0,
                'reason' => __(':count of :total transactions are single item in the usual category (lower bound :bound).', [
                    'count' => $profile->single_item_dominant_count,
                    'total' => $profile->sample_size,
                    'bound' => number_format($wilson, 2),
                ]),
            ];
        }

        // Count the transactions still needed, assuming every new one matches the usual pattern
        $missing = null;
        for ($k = 1; $k <= 200; $k++) {
            $total = $profile->sample_size + $k;
            if ($total >= $minHistory && self::wilsonLowerBound($profile->single_item_dominant_count + $k, $total) >= $wilsonMin) {
                $missing = $k;
                break;
            }
        }

        return [
            'qualifies' => false,
            'missing' => $missing,
            'reason' => $missing === null
                ? __('Too few single-item transactions in one category (lower bound :bound, needs :min).', ['bound' => number_format($wilson, 2), 'min' => number_format($wilsonMin, 2)])
                : __('Needs :missing more matching transactions (lower bound :bound, needs :min).', ['missing' => $missing, 'bound' => number_format($wilson, 2), 'min' => number_format($wilsonMin, 2)]),
        ];
    }

    /**
     * The payees of the user sorted into the four candidate groups. A payee can be in several groups.
     *
     * @return array{qualifying: array, near: array, itemization_mismatch: array, template_candidates: array}
     */
    public function candidates(User $user): array
    {
        $settings = $this->settingsResolver->resolveForUser($user);
        $wilsonMin = (float) $settings['auto_record_wilson_min'];
        $groups = ['qualifying' => [], 'near' => [], 'itemization_mismatch' => [], 'template_candidates' => []];

        $payeesWithTemplate = $user->transactionTemplates()->whereNotNull('payee_id')->pluck('payee_id')->all();

        $profiles = PayeeProfile::query()
            ->whereHas('payee', fn ($query) => $query->where('user_id', $user->id)->where('config_type', 'payee'))
            ->with(['payee.config', 'dominantCategory.parent'])
            ->get();

        $currencies = $this->currenciesFor($profiles);
        $payeesWithActiveSchedule = $this->payeeIdsWithActiveSchedule($user);

        foreach ($profiles as $profile) {
            $payee = $profile->payee;
            /** @var Payee $config */
            $config = $payee->config;
            $qualification = $this->qualification($profile, $config->auto_record_policy, $settings);
            $row = $this->row($profile, $config, $qualification, $currencies->get($profile->typical_account_ids[0] ?? 0), in_array($payee->id, $payeesWithActiveSchedule, true));

            if ($qualification['qualifies']) {
                $groups['qualifying'][] = $row + ['reason' => $qualification['reason']];
            } elseif ($config->auto_record_policy !== Payee::POLICY_NEVER
                && $profile->dominant_category_id !== null
                && ((float) $profile->wilson_lower >= $wilsonMin - self::NEAR_WILSON_MARGIN
                    || ($profile->sample_size < $settings['auto_record_min_history'] && $profile->single_item_dominant_count === $profile->sample_size))
            ) {
                $groups['near'][] = $row + ['reason' => $qualification['reason']];
            }

            if ($profile->sample_size >= self::MIN_SAMPLE_FOR_HINTS) {
                $multi = (float) $profile->multi_item_share;

                if (! $config->itemization_expected && $multi >= self::MULTI_ITEM_SHARE_FOR_MISMATCH) {
                    $groups['itemization_mismatch'][] = $row + ['reason' => __(':percent% of the transactions have several items, but this payee is set to summary sufficient.', ['percent' => round($multi * 100)])];
                } elseif ($config->itemization_expected && $multi <= self::SINGLE_ITEM_SHARE_FOR_MISMATCH) {
                    $groups['itemization_mismatch'][] = $row + ['reason' => __('Almost every transaction has a single item, but this payee is set to itemization expected.')];
                }

                if ((float) $profile->amount_mode_share >= self::TEMPLATE_AMOUNT_MODE_SHARE
                    && $profile->single_item_dominant_count === $profile->sample_size
                    && ! in_array($payee->id, $payeesWithTemplate, true)
                ) {
                    $groups['template_candidates'][] = $row + ['reason' => __(':percent% of the transactions have the same amount in one category, and there is no template yet.', ['percent' => round((float) $profile->amount_mode_share * 100)])];
                }
            }
        }

        return $groups;
    }

    /**
     * IDs of the user's account entities that are on either side of an active schedule.
     *
     * @return array<int, int>
     */
    private function payeeIdsWithActiveSchedule(User $user): array
    {
        $details = DB::table('transactions')
            ->join('transaction_schedules', 'transaction_schedules.transaction_id', '=', 'transactions.id')
            ->join('transaction_details_standard', 'transaction_details_standard.id', '=', 'transactions.config_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', true)
            ->where('transaction_schedules.active', true)
            ->get(['transaction_details_standard.account_from_id', 'transaction_details_standard.account_to_id']);

        return $details->flatMap(fn (stdClass $row) => [(int) $row->account_from_id, (int) $row->account_to_id])->unique()->values()->all();
    }

    /**
     * The newest standard withdrawals and deposits of the payee, schedules excluded.
     *
     * @return Collection<int, stdClass>
     */
    private function windowTransactions(AccountEntity $payee): Collection
    {
        return DB::table('transactions')
            ->join('transaction_details_standard', 'transaction_details_standard.id', '=', 'transactions.config_id')
            ->where('transactions.user_id', $payee->user_id)
            ->where('transactions.config_type', 'standard')
            ->where('transactions.schedule', false)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('transactions.transaction_type', 'withdrawal')->where('transaction_details_standard.account_to_id', $payee->id))
                ->orWhere(fn ($q) => $q->where('transactions.transaction_type', 'deposit')->where('transaction_details_standard.account_from_id', $payee->id)))
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->limit(self::WINDOW)
            ->get([
                'transactions.id',
                'transactions.transaction_type',
                'transaction_details_standard.account_from_id',
                'transaction_details_standard.account_to_id',
                'transaction_details_standard.amount_from',
                'transaction_details_standard.amount_to',
            ]);
    }

    /**
     * @param  array<int, int>  $transactionIds
     * @return Collection<int|string, Collection<int, stdClass>>
     */
    private function itemsByTransaction(array $transactionIds): Collection
    {
        return DB::table('transaction_items')
            ->whereIn('transaction_id', $transactionIds)
            ->get(['transaction_id', 'category_id'])
            ->groupBy('transaction_id');
    }

    /** The amount on the account side: what left the account for a withdrawal, what arrived for a deposit. */
    private function amountOf(object $transaction): BigDecimal
    {
        return BigDecimal::of((string) ($transaction->transaction_type === 'withdrawal' ? $transaction->amount_from : $transaction->amount_to))->toScale(4, RoundingMode::HalfUp);
    }

    /** @param Collection<int, BigDecimal> $sortedAmounts */
    private function median(Collection $sortedAmounts): BigDecimal
    {
        $count = $sortedAmounts->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $sortedAmounts[$middle]
            : $sortedAmounts[$middle - 1]->plus($sortedAmounts[$middle])->dividedBy(2, 4, RoundingMode::HalfUp);
    }

    private function share(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part / $total, 4);
    }

    /**
     * @param  array{qualifies: bool, missing: ?int, reason: string}  $qualification
     * @return array<string, mixed>
     */
    private function row(PayeeProfile $profile, Payee $config, array $qualification, ?Currency $currency, bool $hasActiveSchedule): array
    {
        /** @var Category|null $category */
        $category = $profile->dominantCategory;

        return [
            'payee_id' => $profile->account_entity_id,
            'name' => $profile->payee->name,
            'active' => (bool) $profile->payee->active,
            'has_active_schedule' => $hasActiveSchedule,
            'auto_record_policy' => $config->auto_record_policy,
            'itemization_expected' => $config->itemization_expected,
            'sample_size' => $profile->sample_size,
            'dominant_category_id' => $profile->dominant_category_id,
            'dominant_category' => $category?->full_name,
            'dominant_share' => $profile->dominant_share,
            'wilson_lower' => $profile->wilson_lower,
            'multi_item_share' => $profile->multi_item_share,
            'amount_median' => $profile->amount_median,
            'amount_min' => $profile->amount_min,
            'amount_max' => $profile->amount_max,
            'amount_mode_share' => $profile->amount_mode_share,
            'missing_transactions' => $qualification['missing'],
            'currency' => $currency,
        ];
    }

    /**
     * The currency the profile amounts are in: the one of the payee's most typical account.
     */
    public function currencyOf(PayeeProfile $profile): ?Currency
    {
        return $this->currenciesFor(collect([$profile]))->first();
    }

    /**
     * @param  Collection<int, PayeeProfile>  $profiles
     * @return Collection<int, Currency> Keyed by account entity ID
     */
    private function currenciesFor(Collection $profiles): Collection
    {
        $accountIds = $profiles
            ->map(fn (PayeeProfile $profile) => $profile->typical_account_ids[0] ?? null)
            ->filter()
            ->unique()
            ->values();

        $currencies = collect();

        foreach (AccountEntity::query()->whereIn('id', $accountIds)->with('config.currency')->get() as $account) {
            if ($account->config instanceof Account) {
                $currencies->put($account->id, $account->config->currency);
            }
        }

        return $currencies;
    }
}
