<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivedMail extends Model
{
    use HasFactory;

    protected $table = 'received_mails';

    protected $fillable = [
        'message_id',
        'user_id',
        'subject',
        'html',
        'text',
        'processed',
        'handled',
        'transaction_data',
        'transaction_id',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'handled' => 'boolean',
        'transaction_data' => 'array',
    ];

    public function scopeUnhandled($query)
    {
        return $query->where('handled', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
