<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\CurrencyTrait;
use App\Services\InflationCalculator;
use App\Services\RecurrenceRuleService;
use App\Support\ScheduleInstance;
use Bkwld\Cloner\Cloneable;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\Carbon;
use Database\Factories\TransactionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * App\Models\Transaction
 *
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $date
 * @property TransactionTypeEnum $transaction_type
 * @property bool $reconciled
 * @property bool $schedule
 * @property string|null $comment
 * @property string|null $config_type
 * @property int|null $config_id
 * @property int|null $currency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TransactionDetailInvestment|TransactionDetailStandard|Model|Eloquent|null $config
 * @property-read \Illuminate\Database\Eloquent\Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @method static Builder|Transaction isSchedule()
 * @method static Builder|Transaction byType($type)
 * @method static TransactionFactory factory(...$parameters)
 * @method static Builder|Transaction newModelQuery()
 * @method static Builder|Transaction newQuery()
 * @method static Builder|Transaction query()
 * @method static Builder|Transaction whereComment($value)
 * @method static Builder|Transaction whereConfigId($value)
 * @method static Builder|Transaction whereConfigType($value)
 * @method static Builder|Transaction whereCreatedAt($value)
 * @method static Builder|Transaction whereDate($value)
 * @method static Builder|Transaction whereId($value)
 * @method static Builder|Transaction whereReconciled($value)
 * @method static Builder|Transaction whereSchedule($value)
 * @method static Builder|Transaction whereTransactionType($value)
 * @method static Builder|Transaction whereUpdatedAt($value)
 * @method static Builder|Transaction whereUserId($value)
 * @property int|null $ai_document_id
 * @property-read Money|null $cashflow_value
 * @property-write Money|string|int|float|null $cashflow_value
 * @property float|null $currencyRateToBase
 * @property BigDecimal|null $sum
 * @property int|null $originalId
 * @property string|null $transactionGroup
 * @property int|null $transactionOperator
 * @property string|null $account_from_name
 * @property string|null $account_to_name
 * @property float|null $amount_from
 * @property float|null $amount_to
 * @property mixed $tags
 * @property mixed $categories
 * @property float|null $quantity
 * @property float|null $price
 * @property float|null $running_total
 * @property bool|null $schedule_first_instance
 * @property-read AiDocument|null $aiDocument
 * @property-read Currency|null $currency
 * @property-read Currency|null $transaction_currency
 * @property-read User $user
 * @method static Builder<static>|Transaction whereAiDocumentId($value)
 * @method static Builder<static>|Transaction whereCashflowValue($value)
 * @method static Builder<static>|Transaction whereCurrencyId($value)
 * @mixin Eloquent
 */
#[Fillable('ai_document_id', 'date', 'transaction_type', 'reconciled', 'schedule', 'comment', 'config_type', 'config_id')]
#[Hidden('config_id')]
#[Appends('transaction_currency')]
class Transaction extends Model
{
    use Cloneable;
    use CurrencyTrait;
    use HasFactory;

    protected $cloneable_relations = [
        'config',
        'transactionItems',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'transaction_type' => TransactionTypeEnum::class,
            'reconciled' => 'boolean',
            'schedule' => 'boolean',
            'cashflow_value' => MoneyCast::class . ':4,resolveCashflowCurrency',
        ];
    }

    /**
     * cashflow_value is always denominated in the transaction's own currency
     * (transaction_currency's fallback-to-base-currency logic already handles the
     * common case where currency_id hasn't been resolved yet).
     */
    public function resolveCashflowCurrency(): Currency
    {
        return $this->transaction_currency;
    }

    public function config(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionItems(): HasMany
    {
        // chaperone() sets each loaded item's "transaction" inverse relation to this same
        // parent instance, so TransactionItem::resolveAmountCurrency() (MoneyCast) never
        // needs a fresh lazy lookup - notably including after the parent has been deleted
        // but is still being serialized in-memory (e.g. TransactionApiController::destroy()).
        return $this->hasMany(TransactionItem::class)->chaperone();
    }

    public function transactionSchedule(): HasOne
    {
        return $this->hasOne(TransactionSchedule::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function aiDocument(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class);
    }

    public function tags()
    {
        return $this->transactionItems
            ->pluck('tag')
            ->collapse()
            ->map(fn ($tag) => $tag->withoutRelations())
            ->unique('id');
    }

    public function categories()
    {
        return $this->transactionItems
            ->pluck('category')
            ->unique('id');
    }

    public function isStandard(): bool
    {
        return $this->config_type === 'standard';
    }

    public function isInvestment(): bool
    {
        return $this->config_type === 'investment';
    }

    /**
     * Scope to filter transactions that are real schedules (schedule = true).
     * The schedule = false case is expressed inline (where('schedule', false)) at the few call
     * sites that need it, rather than kept as a named scope.
     */
    #[Scope]
    protected function isSchedule(Builder $query): Builder
    {
        return $query->where('schedule', true);
    }

    /**
     * Create a dynamic scope to filter transactions by their type
     */
    #[Scope]
    protected function byType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'standard' => $query->where('config_type', 'standard'),
            'investment' => $query->where('config_type', 'investment'),
            default => $query,
        };
    }

    /**
     * Scope to filter transactions that are eligible for item merging.
     *
     * A transaction qualifies when it is a standard non-schedule
     * transaction AND has at least two transaction items that share the same
     * category_id and have an empty (null or blank) comment — i.e. there is
     * actual merge work to be done.
     */
    #[Scope]
    protected function eligibleForItemMerge(Builder $query): Builder
    {
        return $query
            ->where('config_type', 'standard')
            ->where('schedule', false)
            ->whereExists(function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('transaction_items')
                    ->whereColumn('transaction_id', 'transactions.id')
                    ->where(function ($q): void {
                        $q->whereNull('comment')
                            ->orWhere('comment', '');
                    })
                    ->groupBy('transaction_id', 'category_id')
                    ->havingRaw('COUNT(*) >= 2');
            });
    }

    // Generic function to load necessary relations, based on transaction type
    public function loadDetails(): void
    {
        if ($this->transaction_type->isStandard()) {
            $this->loadStandardDetails();
            return;
        }
        if ($this->transaction_type->isInvestment()) {
            $this->loadInvestmentDetails();
            return;
        }
    }

    private function loadStandardDetails(): void
    {
        $this->loadMissing([
            'config',
            'config.accountFrom',
            'config.accountTo',
            'currency',
            'transactionSchedule',
            'transactionItems',
            'transactionItems.tags',
            'transactionItems.category',
        ]);

        if ($this->transaction_type === TransactionTypeEnum::WITHDRAWAL) {
            $this->loadMissing([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
            ]);
        }

        if ($this->transaction_type === TransactionTypeEnum::DEPOSIT) {
            $this->loadMissing([
                'config.accountTo.config',
                'config.accountTo.config.currency',
                'config.accountFrom.config',
            ]);
        }

        if ($this->transaction_type === TransactionTypeEnum::TRANSFER) {
            $this->loadMissing([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
                'config.accountTo.config.currency',
            ]);
        }
    }

    private function loadInvestmentDetails(): void
    {
        $this->loadMissing([
            'config',
            'config.account',
            'config.account.config',
            'config.account.config.currency',
            'config.investment',
            'currency',
            'transactionSchedule',
        ]);
    }

    public function getTransactionCurrencyAttribute(): ?Currency
    {
        // First try to use the loaded relationship (from eager loading)
        if ($this->relationLoaded('currency') && $this->currency) {
            return $this->currency;
        }

        // If currency_id is null, return base currency
        if ($this->currency_id === null) {
            return $this->getBaseCurrency($this->user_id);
        }

        // Use cached collection for lookup
        $allCurrencies = $this->getAllCurrencies($this->user_id);
        $currency = $allCurrencies->get($this->currency_id);

        // If not found in cache, fall back to base currency
        return $currency ?? $this->getBaseCurrency($this->user_id);
    }

    /**
     * Generates a collection of scheduled transaction instances based on the transaction schedule.
     *
     * @param Carbon|null $constraintStart The start date constraint for generating instances. Defaults to the next scheduled date.
     * @param Carbon|null $maxLookAhead The maximum look-ahead date for generating instances. Defaults to the user's end date.
     * @param int|null $virtualLimit The virtual limit for the number of instances to generate. Defaults to 500.
     *
     * @return Collection<int, ScheduleInstance> A collection of virtual (never-persisted) schedule instances.
     */
    public function scheduleInstances(
        ?Carbon $constraintStart = null,
        ?Carbon $maxLookAhead = null,
        ?int $virtualLimit = 500
    ): Collection {
        $scheduleInstances = new Collection();

        if ($maxLookAhead === null) {
            $maxLookAhead = $this->user->end_date;
        }

        if ($constraintStart === null) {
            if ($this->transactionSchedule->next_date === null) {
                // No next_date means the schedule is exhausted (e.g. a count-limited
                // rule whose occurrences are all in the past) - there is nothing left
                // to generate, so don't fall back to "now" as a fresh start date.
                return $scheduleInstances;
            }

            $constraintStart = new Carbon($this->transactionSchedule->next_date);
        }
        $constraintStart->startOfDay();

        if ($this->transactionSchedule->end_date === null) {
            $endDate = $maxLookAhead;
        } else {
            $endDate = new Carbon($this->transactionSchedule->end_date);
        }
        $endDate->startOfDay();

        // Keyed on both the schedule's own updated_at and this transaction row's updated_at:
        // only the date list is cached below (not $baseAttributes/$baseRelations, which are
        // rebuilt fresh per call from $this), but keying on both is the safe choice for anyone
        // who extends this cache to also cover per-occurrence attributes later.
        $cacheKey = "schedule-occurrences:{$this->transactionSchedule->id}:"
            . "{$this->transactionSchedule->updated_at?->timestamp}:{$this->updated_at?->timestamp}:"
            . "{$constraintStart->toDateString()}:{$endDate->toDateString()}:{$virtualLimit}";

        $dateStrings = Cache::remember($cacheKey, now()->addHour(), function () use (
            $constraintStart,
            $endDate,
            $virtualLimit,
        ) {
            $recurrence = (new RecurrenceRuleService())->getRecurrenceBetween(
                $this->transactionSchedule->start_date,
                $this->transactionSchedule->frequency,
                $this->transactionSchedule->interval,
                $this->transactionSchedule->end_date,
                $this->transactionSchedule->count,
                $this->transactionSchedule->by_day,
                $this->transactionSchedule->by_month,
                \Illuminate\Support\Carbon::instance($constraintStart),
                \Illuminate\Support\Carbon::instance($endDate),
                $virtualLimit,
            );

            return collect($recurrence)
                ->map(fn ($occurrence) => Carbon::instance($occurrence->getStart())->toDateString())
                ->values()
                ->all();
        });

        // Every virtual occurrence shares the same attributes/relations as $this - only the
        // date and "is this the first instance" flag differ per occurrence. Resolving the
        // handful of cast/appended values once here, instead of via a full Eloquent::replicate()
        // per occurrence, is what avoids the per-occurrence model-instantiation cost profiled
        // in forecast-performance.md; a plain array copy per occurrence is essentially free.
        $baseAttributes = $this->getAttributes();
        unset(
            $baseAttributes[$this->getKeyName()],
            $baseAttributes[$this->getCreatedAtColumn()],
            $baseAttributes[$this->getUpdatedAtColumn()],
            $baseAttributes['config_id'],
        );
        $baseAttributes['transaction_type'] = $this->transaction_type;
        $baseAttributes['reconciled'] = $this->reconciled;
        $baseAttributes['schedule'] = $this->schedule;
        $baseAttributes['cashflow_value'] = $this->cashflow_value;
        $baseAttributes['transaction_currency'] = $this->transaction_currency;
        $baseAttributes['originalId'] = $this->id;
        $baseAttributes['transactionGroup'] = 'forecast';

        $baseRelations = $this->relations;

        // Some features need to know which is the first instance
        $first = true;

        $inflationCalculator = new InflationCalculator();
        $scheduleStartDate = new \Illuminate\Support\Carbon($this->transactionSchedule->start_date);
        $inflationRate = $this->transactionSchedule->inflation;

        foreach ($dateStrings as $dateString) {
            $attributes = $baseAttributes;
            $instanceDate = \Illuminate\Support\Carbon::parse($dateString);
            $attributes['date'] = $instanceDate;
            $attributes['schedule_first_instance'] = $first;
            $attributes['inflationMultiplier'] = (string) $inflationCalculator->applyAnnualRate(
                1.0,
                $inflationRate,
                $scheduleStartDate,
                $instanceDate,
            );

            $scheduleInstances->push(new ScheduleInstance($attributes, $baseRelations));

            $first = false;
        }

        return $scheduleInstances;
    }
}
