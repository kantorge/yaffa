<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Builder;

class Payee extends AccountEntity
{
    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payees';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id',
        'category_suggestion_dismissed'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'category_suggestion_dismissed' => 'datetime',
    ];

    public function config()
    {
        return $this->morphOne(AccountEntity::class, 'config');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions()
    {
        $account = $this;

        return Transaction::where('schedule', 0)
        ->where('budget', 0)
        ->whereHasMorph(
            'config',
            [TransactionDetailStandard::class],
            function (Builder $query) use ($account) {
                $query->Where('account_from_id', $account->id);
                $query->orWhere('account_to_id', $account->id);
            }
        )
        ->get();
    }

    public function getLatestTransactionDateAttribute()
    {
        return $this->transactions()->pluck('date')->max();
    }

    public function getFirstTransactionDateAttribute()
    {
        return $this->transactions()->pluck('date')->min();
    }

    public function getTransactionCountAttribute()
    {
        return $this->transactions()->count();
    }
}