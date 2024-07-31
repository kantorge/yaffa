<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GocardlessAccount extends Model
{
    use HasUuids;
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'requisition_id',
        'name',
        'iban',
        'currency_code',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(GocardlessRequisition::class, 'requisition_id');
    }

    public function yaffaAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->HasMany(GocardlessTransaction::class);
    }
}
