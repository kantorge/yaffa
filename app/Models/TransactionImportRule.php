<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionImportRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'description_pattern',
        'use_regex',
        'action',
        'transfer_account_id',
        'transaction_type_id',
        'priority',
        'active',
    ];

    protected $casts = [
        'use_regex' => 'boolean',
        'active' => 'boolean',
        'priority' => 'integer',
        'transaction_type_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_id');
    }

    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'transfer_account_id');
    }

    /**
     * Check if a description matches this rule's pattern.
     */
    public function matches(string $description): bool
    {
        if ($this->use_regex) {
            return (bool) preg_match($this->description_pattern, $description);
        }
        
        return stripos($description, $this->description_pattern) !== false;
    }
}
