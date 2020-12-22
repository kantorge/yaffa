<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
//use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class Transaction extends Model
{

    //protected $softDelete = true;

    //protected $dates = ['deleted_at'];

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

    //protected $with = ['config'];

    protected $casts = [
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
            ->each(function ($item, $key) use (&$tags) {
                $item->tags->each(function($tag) use (&$tags) {
                    $tags[$tag->id] = $tag->name;
                } );

            });

        return $tags;
    }

    public function categories()
    {
        $categories = [];

        $this->transactionItems()
            ->each(function ($item, $key) use (&$categories) {
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
        foreach($transactionArray['transaction_items'] as $item) {
            foreach($item['tags'] as $tag) {
                $tags[$tag['id']] = $tag['name'];
            };
        };

        return $tags;
    }

    //TODO: how this can be achieved without converting data to array AND without additional database queries
    public function getCategoriesArray()
    {
        $transactionArray = $this->toArray();
        $categories = [];
        foreach($transactionArray['transaction_items'] as $item) {
            if ($item['category']) {
                $categories[$item['category_id']] = $item['category']['full_name'];
            }
        };

        return $categories;
    }

    function delete()
    {
        $this->config()->delete();

        parent::delete();
    }

    /*
    public function transactionDetailsStandard()
    {
        return $this->hasMany(TransactionDetailStandard::class);
    }
    */
}
