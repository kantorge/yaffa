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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|Eloquent $config
 * @property-read \Illuminate\Database\Eloquent\Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 *
 * @method static Builder|Transaction byScheduleType($type)
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
 *
 * @property-read \App\Models\Currency|null $transaction_currency
 * @property-read \App\Models\User $user
 *
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
        'user_id',
        'account_from_id',
        'account_to_id',
        'amount_primary',
        'amount_secondary',
        'price',
        'quantity',
        'commission',
        'tax',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime',
        'reconciled' => 'boolean',
        'schedule' => 'boolean',
        'budget' => 'boolean',
        'amount_primary' => 'float',
        'amount_secondary' => 'float',
        'price' => 'float',
        'quantity' => 'float',
        'commission' => 'float',
        'tax' => 'float',
    ];

    protected $appends = [
        'transaction_currency',
    ];

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

    public function accountFrom(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_from_id');
    }

    public function accountTo(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_to_id');
    }

    /**
     * Get the investment details associated with the transaction.
     * If the transaction is not an investment, return null.
     * Otherwise, return the investment from either the account from or the account to field.
     */
    public function investment(): ?TransactionDetailInvestment
    {
        if ($this->transactionType->quantity_operator !== 'investment') {
            return null;
        }

        if ($this->transactionType->quantity_operator === 'plus') {
            return $this->accountTo->config;
        }

        if ($this->transactionType->quantity_operator === 'minus') {
            return $this->accountFrom->config;
        }

        // Dividend and yield
        return $this->accountFrom()->config;
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

    /**
     * Create a dynamic scope to filter transactions by schedule and/or budget flag
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
     * Get a numeric value representing the net financial result of the current transaction.
     * Reference account must be passed, as result for some transaction types (e.g. transfer) depend on related account.
     */
    public function cashflowValue(AccountEntity $account = null): float|int
    {
        if ($this->config_type === 'transaction_detail_standard') {
            $operator = $this->transactionType->amount_operator ?? ($this->config->account_from_id === $account->id ? 'minus' : 'plus');

            return $operator === 'minus' ? -$this->config->amount_from : $this->config->amount_to;
        }

        if ($this->config_type === 'transaction_detail_investment') {
            $operator = $this->transactionType->amount_operator;
            if ($operator) {
                return ($operator === 'minus' ? -1 : 1) * $this->config->price * $this->config->quantity
                    + $this->config->dividend
                    - $this->config->tax
                    - $this->config->commission;
            }
        }

        return 0;
    }

    // Generic function to load necessary relations, based on transaction type
    public function loadDetails(): void
    {
        if ($this->transactionType->type === 'standard') {
            $this->loadStandardDetails();
        } elseif ($this->transactionType->type === 'investment') {
            $this->loadInvestmentDetails();
        }
    }

    private function loadStandardDetails(): void
    {
        $this->loadMissing([
            'accountFrom',
            'accountTo',
            'transactionSchedule',
            'transactionType',
            'transactionItems',
            'transactionItems.tags',
            'transactionItems.category',
        ]);

        if ($this->transactionType->name === 'withdrawal') {
            $this->loadMissing([
                'accountFrom.config',
                'accountFrom.config.currency',
                'accountTo.config',
            ]);
        }

        if ($this->transactionType->name === 'deposit') {
            $this->loadMissing([
                'accountTo.config',
                'accountTo.config.currency',
                'accountFrom.config',
            ]);
        }

        if ($this->transactionType->name === 'transfer') {
            $this->loadMissing([
                'accountFrom.config',
                'accountFrom.config.currency',
                'accountTo.config',
                'accountTo.config.currency',
            ]);
        }
    }

    private function loadInvestmentDetails(): void
    {
        $this->loadMissing([
            'accountFrom.config',
            'accountTo.config',
            'transactionSchedule',
            'transactionType',
        ]);
    }

    public function getTransactionCurrencyAttribute(): ?Currency
    {
        return $this->transactionCurrency() ?? $this->getBaseCurrency();
    }

    public function transactionCurrency()
    {
        if ($this->config_type === 'transaction_detail_standard') {
            if ($this->transaction_type === 'deposit') {
                $this->loadMissing([
                    'accountTo',
                    'accountTo.config',
                    'accountTo.config.currency',
                ]);

                return $this->config?->accountTo?->config->currency;
            }

            return $this->config?->accountFrom?->config->currency;
        }

        if ($this->config_type === 'transaction_detail_investment') {
            $this->loadMissing([
                'config',
                'config.account',
                'config.account.config',
                'config.account.config.currency',
            ]);

            return $this->config->account->config->currency;
        }

        return null;
    }

    public function scheduleInstances(Carbon $constraintStart = null, Carbon $maxLookAhead = null, ?int $virtualLimit = 500)
    {
        $scheduleInstances = new Collection();

        if ($maxLookAhead === null) {
            $maxLookAhead = Auth::user()->end_date;
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
