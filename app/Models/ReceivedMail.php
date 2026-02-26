<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * App\Models\ReceivedMail
 *
 * @property int $id
 * @property string $subject
 * @property string $html
 * @property string $text
 * @property string $message_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AiDocument|null $aiDocument
 * @property-read User $user
 * @method static \Database\Factories\ReceivedMailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereUserId($value)
 * @mixin Eloquent
 */
class ReceivedMail extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'subject',
        'html',
        'text',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiDocument(): HasOne
    {
        return $this->hasOne(AiDocument::class);
    }
}
