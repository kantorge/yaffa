<?php

namespace App\Models;

use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEntity extends Model
{
    use HasFactory;

    protected $table = 'account_entities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'active',
        'config_type',
        'config_id',
        'user_id',
    ];

    protected $hidden = ['config_id'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function config()
    {
        return $this->morphTo();
    }

    public function transactionDetailStandardFrom()
    {
        return $this->hasMany(TransactionDetailStandard::class, 'account_from_id');
    }

    public function transactionDetailStandardTo()
    {
        return $this->hasMany(TransactionDetailStandard::class, 'account_to_id');
    }

    // Relation to transactions where this account is the from account or the to account
    public function transactionsFrom()
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailStandard::class,
            'account_from_id',
            'config_id',
            'id',
            'id'
        );
    }
    public function transactionsTo()
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailStandard::class,
            'account_to_id',
            'config_id',
            'id',
            'id'
        );
    }

    /**
     * Scope a query to only include active entities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope a query to only include accounts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccounts($query)
    {
        return $query->where('config_type', 'account');
    }

    /**
     * Scope a query to only include payees.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePayees($query)
    {
        return $query->where('config_type', 'payee');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
