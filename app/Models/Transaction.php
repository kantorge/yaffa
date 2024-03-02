<?php

namespace App\Models;

use App\Http\Traits\CurrencyTrait;
use Bkwld\Cloner\Cloneable;
use Carbon\Carbon;
use Database\Factories\TransactionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

/**
 * App\Models\Transaction
 *
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $date
 * @property int $transaction_type_id
 * @property bool $reconciled
 * @property bool $schedule
 * @property bool $budget
 * @property string|null $comment
 * @property string|null $config_type
 * @property int|null $config_id
 * @property int|null $currency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|Eloquent $config
 * @property-read \Illuminate\Database\Eloquent\Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 * @method static Builder|Transaction byScheduleType($type)
 * @method static Builder|Transaction byType($type)
 * @method static TransactionFactory factory(...$parameters)
 * @method static Builder|Transaction newModelQuery()
 * @method static Builder|Transaction newQuery()
 * @method static Builder|Transaction query()
 * @method static Builder|Transaction whereBudget($value)
 * @method static Builder|Transaction whereComment($value)
 * @method static Builder|Transaction whereConfigId($value)
 * @method static Builder|Transaction whereConfigType($value)
 * @method static Builder|Transaction whereCreatedAt($value)
 * @method static Builder|Transaction whereDate($value)
 * @method static Builder|Transaction whereId($value)
 * @method static Builder|Transaction whereReconciled($value)
 * @method static Builder|Transaction whereSchedule($value)
 * @method static Builder|Transaction whereTransactionTypeId($value)
 * @method static Builder|Transaction whereUpdatedAt($value)
 * @method static Builder|Transaction whereUserId($value)
 * @mixin Eloquent
 */
class Transaction extends Model
{
    use Cloneable;
    use CurrencyTrait;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'date',
        'transaction_type_id',
        'reconciled',
        'schedule',
        'budget',
        'comment',
        'config_type',
        'config_id',
        'user_id',
    ];

    protected $hidden = [
        'config_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'reconciled' => 'boolean',
        'schedule' => 'boolean',
        'budget' => 'boolean',
    ];

    protected $appends = [
        'transaction_currency',
    ];

    protected $cloneable_relations = [
        'config',
    ];

    public function config(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function transactionSchedule(): HasOne
    {
        return $this->hasOne(TransactionSchedule::class);
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
     * Create a dynamic scope to filter transactions by schedule and/or budget flag
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeByScheduleType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'schedule' => $query->where('schedule', true),
            'schedule_only' => $query->where('schedule', true)->where('budget', false),
            'budget' => $query->where('budget', true),
            'budget_only' => $query->where('budget', true)->where('schedule', false),
            'both' => $query->where('schedule', true)->where('budget', true),
            'any' => $query->where('schedule', true)->orWhere('budget', true),
            'none' => $query->where('schedule', false)->where('budget', false),
            default => $query,
        };
    }

    /**
     * Create a dynamic scope to filter transactions by their type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'standard' => $query->where('config_type', 'standard'),
            'investment' => $query->where('config_type', 'investment'),
            default => $query,
        };
    }

    /**
     * Get a numeric value representing the net financial result of the current transaction.
     * Reference account must be passed, as result for some transaction types (e.g. transfer) depend on related account.
     *
     * @param AccountEntity|null $account
     * @return float|int
     */
    public function accountBalanceChange(AccountEntity $account = null): float|int
    {
        if ($this->isStandard()) {
            $operator = $this->transactionType->amount_operator ?? ($this->config->account_from_id === $account->id ? 'minus' : 'plus');

            return $operator === 'minus' ? -$this->config->amount_from : $this->config->amount_to;
        }

        if ($this->isInvestment()) {
            return $this->cashflow_value ?? 0;
        }

        return 0;
    }

    // Generic function to load necessary relations, based on transaction type
    public function loadDetails(): void
    {
        if ($this->transactionType->type === 'standard') {
            $this->loadStandardDetails();
            return;
        }
        if ($this->transactionType->type === 'investment') {
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
            'transactionSchedule',
            'transactionType',
            'transactionItems',
            'transactionItems.tags',
            'transactionItems.category',
        ]);

        if ($this->transactionType->name === 'withdrawal') {
            $this->loadMissing([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
            ]);
        }

        if ($this->transactionType->name === 'deposit') {
            $this->loadMissing([
                'config.accountTo.config',
                'config.accountTo.config.currency',
                'config.accountFrom.config',
            ]);
        }

        if ($this->transactionType->name === 'transfer') {
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
            'transactionSchedule',
            'transactionType',
        ]);
    }

    public function getTransactionCurrencyAttribute(): ?Currency
    {
        return Currency::findOr($this->currency_id, fn () => $this->getBaseCurrency());
    }

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
            $constraintStart = new Carbon($this->transactionSchedule->next_date);
        }
        $constraintStart->startOfDay();

        $rule = new Rule();
        $rule->setStartDate(new Carbon($this->transactionSchedule->start_date));

        if ($this->transactionSchedule->end_date) {
            $rule->setUntil(new Carbon($this->transactionSchedule->end_date));
        }

        $rule->setFreq($this->transactionSchedule->frequency);

        if ($this->transactionSchedule->count) {
            $rule->setCount($this->transactionSchedule->count);
        }
        if ($this->transactionSchedule->interval) {
            $rule->setInterval($this->transactionSchedule->interval);
        }

        $transformer = new ArrayTransformer();

        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->setVirtualLimit($virtualLimit);
        $transformerConfig->enableLastDayOfMonthFix();
        $transformer->setConfig($transformerConfig);

        if ($this->transactionSchedule->end_date === null) {
            $endDate = $maxLookAhead;
        } else {
            $endDate = new Carbon($this->transactionSchedule->end_date);
        }
        $endDate->startOfDay();

        $constraint = new BetweenConstraint($constraintStart, $endDate, true);

        $first = true;

        foreach ($transformer->transform($rule, $constraint) as $instance) {
            $newTransaction = $this->replicate();

            $newTransaction->originalId = $this->id;
            $newTransaction->date = $instance->getStart();
            $newTransaction->transactionGroup = 'forecast';
            $newTransaction->schedule_first_instance = $first;

            $scheduleInstances->push($newTransaction);

            $first = false;
        }

        return $scheduleInstances;
    }
}
