<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GocardlessTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'gocardless_account_id',
        'user_id',
        'transaction_id',
        'booking_date',
        'value_date',
        'transaction_amount',
        'currency_code',
        'description',
        'debtor_name',
        'creditor_name',
        'status',
        'raw_data',
    ];

    public $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'raw_data' => 'array',
    ];
}
