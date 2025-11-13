<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportedInvestmentRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'reference',
        'date',
        'transaction_type',
        'description',
        'amount',
        'balance',
        'raw_data',
        'processed',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'processed' => 'boolean',
        'date' => 'datetime',
        'amount' => 'decimal:8',
        'balance' => 'decimal:8',
    ];
}
