<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\ReceivedMail
 *
 * @property int $id
 * @property string $subject
 * @property string $html
 * @property string $text
 * @property bool $processed
 * @property bool $handled
 * @mixin Eloquent
 */
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

    protected function casts(): array
    {
        return [
            'processed' => 'boolean',
            'handled' => 'boolean',
            'transaction_data' => 'array',
        ];
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

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
