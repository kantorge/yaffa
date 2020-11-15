<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
                $categories[$item->category_id] = $item->category->full_name;
            });

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
