<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

class Transaction extends Model
{
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

    protected $hidden = ['config_id'];

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
        $tags = [];

        $this->transactionItems()
            ->each(function ($item) use (&$tags) {
                $item->tags->each(function ($tag) use (&$tags) {
                    $tags[$tag->id] = $tag->name;
                });
            });

        return $tags;
    }

    public function categories()
    {
        $categories = [];

        $this->transactionItems()
            ->each(function ($item) use (&$categories) {
                if ($item->category) {
                    $categories[$item->category_id] = $item->category->full_name;
                }
            });

        return $categories;
    }

    /**
     * Create a scope for basic transactions, which are neither scheduled nor budgeted.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBasicTransaction($query)
    {
        return $query->where('schedule', false)->where('budget', false);
    }

    /**
     * Create a dynamic scope to filter transactions by schedule and/or budget flag
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        switch ($type) {
            case 'schedule':
                return $query->where('schedule', true)->where('budget', false);
            case 'budget':
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

    //TODO: how this can be achieved without converting data to array AND without additional database queries
    public function getTagsArray()
    {
        $transactionArray = $this->toArray();
        $tags = [];
        foreach ($transactionArray['transaction_items'] as $item) {
            foreach ($item['tags'] as $tag) {
                $tags[$tag['id']] = $tag['name'];
            }
        }

        return $tags;
    }

    //TODO: how this can be achieved without converting data to array AND without additional database queries
    public function getCategoriesArray()
    {
        $transactionArray = $this->toArray();
        $categories = [];
        foreach ($transactionArray['transaction_items'] as $item) {
            if ($item['category']) {
                $categories[$item['category_id']] = $item['category']['full_name'];
            }
        }

        return $categories;
    }

    public function delete()
    {
        $this->config()->delete();

        parent::delete();
    }

    /**
     * Get a numeric value representing the net financial result of the current transaction.
     * Reference account must be passed, as result for some transaction types (e.g. transfer) depend on related account.
     *
     * @param App\Models\AccountEntity $account
     * @return Numeric
     */
    public function cashflowValue(?AccountEntity $account)
    {
        if ($this->config_type === 'transaction_detail_standard') {
            $operator = $this->transactionType->amount_operator ?? ($this->config->account_from_id === $account->id ? 'minus' : 'plus');

            return $operator === 'minus' ? -$this->config->amount_from : $this->config->amount_to;
        }

        if ($this->config_type === 'transaction_detail_investment') {
            $operator = $this->transactionType->amount_operator;
            if ($operator) {
                return ($operator === 'minus'
                    ? -$this->config->price * $this->config->quantity
                    : $this->config->dividend + $this->config->price * $this->config->quantity)
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

        // TODO: this is not needed for all use cases

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

    public function transactionCurrency()
    {
        if ($this->config_type === 'transaction_detail_standard') {
            if ($this->transaction_type === 'deposit') {
                $this->load([
                    'config',
                    'config.accountTo.config.currency',
                ]);

                return $this->config->accountTo->currency;
            }

            return $this->config->accountFrom->currency;
        }

        if ($this->config_type === 'transaction_detail_investment') {
            $this->load([
                'config',
                'config.account.currency',
            ]);

            return $this->config->account->currency;
        }

        return null;
    }

    public function scheduleInstances(?Carbon $constraintStart = null, ?Carbon $maxLookAhead = null, ?int $virtualLimit = 500)
    {
        $scheduleInstances = new Collection();

        if (is_null($maxLookAhead)) {
            $maxLookAhead = (new Carbon())->addYears(1); //TODO: get end date from settings, and/or display default setting
        }

        if (is_null($constraintStart)) {
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

        if (is_null($this->transactionSchedule->end_date)) {
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
