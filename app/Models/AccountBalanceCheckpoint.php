<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_entity_id',
        'checkpoint_date',
        'balance',
        'note',
        'active',
    ];

    protected $casts = [
        'checkpoint_date' => 'datetime',
        'balance' => 'decimal:2',
        'active' => 'boolean',
    ];

    /**
     * Get the user that owns the checkpoint.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the account entity this checkpoint is for.
     */
    public function accountEntity(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class);
    }

    /**
     * Scope to get only active checkpoints.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get checkpoints for a specific account.
     */
    public function scopeForAccount($query, $accountEntityId)
    {
        return $query->where('account_entity_id', $accountEntityId);
    }

    /**
     * Scope to get checkpoints on or before a specific date.
     */
    public function scopeBeforeOrOn($query, $date)
    {
        return $query->where('checkpoint_date', '<=', $date);
    }
}
