<?php

namespace App\Models;

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
 * @property string $message_id
 * @property array|null $transaction_data
 * @property int $user_id
 * @property int|null $transaction_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Transaction|null $transaction
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\ReceivedMailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail query()
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail unhandled()
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail unprocessed()
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereHandled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereTransactionData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReceivedMail whereUserId($value)
 *
 * @mixin \Eloquent
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

    protected $casts = [
        'processed' => 'boolean',
        'handled' => 'boolean',
        'transaction_data' => 'array',
    ];

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
