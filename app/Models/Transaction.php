<?php

namespace App\Models;

use App\Http\Traits\CurrencyTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property-read Model|\Eloquent $config
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read \App\Models\TransactionSchedule|null $transactionSchedule
 * @property-read \App\Models\TransactionType $transactionType
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction byScheduleType($type)
 * @method static \Database\Factories\TransactionFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereBudget($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereConfigId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereConfigType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereReconciled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereTransactionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereUserId($value)
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    use HasFactory;
    use CurrencyTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
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
     * @var array
     */
    protected $casts = [
        'date' => 'datetime',
        'reconciled' => 'boolean',
        'schedule' => 'boolean',
        'budget' => 'boolean',
    ];

    public function config()
    {
        return $this->morphTo();
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function transactionSchedule()
    {
        return $this->hasOne(TransactionSchedule::class);
    }

    public function tags()
    {
        return $this->transactionItems
            ->pluck('tags')
            ->collapse()
            ->map(function ($tag) {
                return $tag->withoutRelations();
            })
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
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByScheduleType($query, $type)
    {
        switch ($type) {
            case 'schedule':
                return $query->where('schedule', true);
            case 'schedule_only':
                return $query->where('schedule', true)->where('budget', false);
            case 'budget':
                return $query->where('budget', true);
            case 'budget_only':
                return $query->where('budget', true)->where('schedule', false);
            case 'both':
                return $query->where('schedule', true)->where('budget', true);
            case 'any':
                return $query->where('schedule', true)->orWhere('budget', true);
            case 'none':
                return $query->where('schedule', false)->where('budget', false);
            default:
                return $query;
        }
    }

    /**
     * Override the default delete method to delete the transaction configuration as well
     *
     * @return bool|null
     */
    public function delete(): bool|null
    {
        $this->config()->delete();
        return parent::delete();
    }

    /**
     * Get a numeric value representing the net financial result of the current transaction.
     * Reference account must be passed, as result for some transaction types (e.g. transfer) depend on related account.
     *
     * @param  \App\Models\AccountEntity  $account
     * @return float
     */
    public function cashflowValue(AccountEntity $account = null)
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

    public function loadStandardDetails()
    {
        $this->load([
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
            $this->load([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
            ]);
        }

        if ($this->transactionType->name === 'deposit') {
            $this->load([
                'config.accountTo.config',
                'config.accountTo.config.currency',
                'config.accountFrom.config',
            ]);
        }

        if ($this->transactionType->name === 'transfer') {
            $this->load([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
                'config.accountTo.config.currency',
            ]);
        }
    }

    public function loadInvestmentDetails()
    {
        $this->load([
            'config',
            'config.account',
            'config.account.currency',
            'transactionSchedule',
            'transactionType',
        ]);
    }

    public function transformToClient()
    {
        // Standard
        if ($this->config_type === 'transaction_detail_standard') {
            return array_merge(
                $this->transformDataCommon(),
                $this->transformDataStandard(),
                [
                    'currency' => $this->transactionCurrency() ?? $this->getBaseCurrency(),
                ]
            );
        }

        // Investment
        if ($this->config_type === 'transaction_detail_investment') {
            return array_merge(
                $this->transformDataCommon(),
                $this->transformDataInvestment(),
                [
                    'currency' => $this->transactionCurrency() ?? $this->getBaseCurrency(),
                ]
            );
        }
    }

    private function transformDataCommon()
    {
        $transaction = $this;

        // Prepare schedule related data if schedule is set
        $schedule = null;
        if ($transaction->transactionSchedule) {
            $schedule = [
                'start_date' => $transaction->transactionSchedule->start_date->toISOString(),
                'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->toISOString() : null),
                'end_date' => ($transaction->transactionSchedule->end_date ? $transaction->transactionSchedule->end_date->toISOString() : null),
                'frequency' => $transaction->transactionSchedule->frequency,
                'count' => $transaction->transactionSchedule->count,
                'interval' => $transaction->transactionSchedule->interval,
            ];
        }

        return [
            'id' => $transaction->id,
            'date' => $transaction->date,  // Change compared to schedule controller
            'transaction_type' => $transaction->transactionType->toArray(),
            'config_type' => $transaction->config_type,
            'schedule_config' => $schedule,
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'comment' => $transaction->comment,
            'reconciled' => $transaction->reconciled,
        ];
    }

    private function transformDataStandard()
    {
        if (! $this->transactionItems) {
            $this->load([
                'transactionItems',
                'transactionItems.category',
                'transactionItems.tags',
            ]);
        }

        // TODO: replace with eager loading
        $allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        $transaction = $this;
        $transactionArray = $this->toArray();

        return [
            'config' => [
                'account_from_id' => $transaction->config->account_from_id,
                'account_from' => [
                    'name' => $allAccounts[$transaction->config->account_from_id] ?? null,
                    'id' => $transaction->config->account_from_id,
                ],
                'account_to_id' => $transaction->config->account_to_id,
                'account_to' => [
                    'name' => $allAccounts[$transaction->config->account_to_id] ?? null,
                    'id' => $transaction->config->account_to_id,
                ],
                'amount_from' => $transaction->config->amount_from,
                'amount_to' => $transaction->config->amount_to,
            ],
            'transaction_items' => $transactionArray['transaction_items'],
            'tags' => $this->tags()->values(),
            'categories' => $this->categories()->values(),
        ];
    }

    private function transformDataInvestment()
    {
        // TODO: replace with eager loading
        $allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        $transaction = $this;
        $amount = $transaction->cashflowValue();

        return [
            'config' => [
                'account_from_id' => $transaction->config->account_id,
                'account_from' => [
                    'name' => $allAccounts[$transaction->config->account_id],
                    'id' => $transaction->config->account_id,
                ],
                'account_to_id' => $transaction->config->investment_id,
                'account_to' => [
                    'name' => $transaction->config->investment->name,
                    'id' => $transaction->config->investment_id,
                ],
                'amount_from' => $amount,
                'amount_to' => $amount,
            ],
            'tags' => [],

            'investment_name' => $transaction->config->investment->name,
            'quantity' => $transaction->config->quantity,
            'price' => $transaction->config->price,
        ];
    }

    public function transactionCurrency()
    {
        if ($this->config_type === 'transaction_detail_standard') {
            if ($this->transaction_type === 'deposit') {
                $this->load([
                    'config',
                    'config.accountTo.config.currency',
                ]);

                return $this->config?->accountTo?->currency;
            }

            return $this->config?->accountFrom?->currency;
        }

        if ($this->config_type === 'transaction_detail_investment') {
            $this->load([
                'config',
                'config.account.config.currency',
            ]);

            return $this->config->account->currency;
        }

        return null;
    }

    public function scheduleInstances(?Carbon $constraintStart = null, ?Carbon $maxLookAhead = null, ?int $virtualLimit = 500)
    {
        $scheduleInstances = new Collection();

        if ($maxLookAhead === null) {
            $maxLookAhead = (new Carbon(config('yaffa.app_end_date')));
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
