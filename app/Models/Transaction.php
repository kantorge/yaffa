<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'config_id'
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
            $operator = $this->transactionType->amount_operator ?? ( $this->config->account_from_id == $account->id ? 'minus' : 'plus');
            return $operator === 'minus' ? -$this->config->amount_from : $this->config->amount_to;
        }

        if ($this->config_type === 'transaction_detail_investment') {
            $operator = $this->transactionType->amount_operator;
            if ($operator) {
                return ($operator === 'minus'
                    ? - $this->config->price * $this->config->quantity
                    : $this->config->dividend + $this->config->price * $this->config->quantity )
                    - $this->config->tax
                    - $this->config->commission;
            }
        }

        return 0;
    }
}
