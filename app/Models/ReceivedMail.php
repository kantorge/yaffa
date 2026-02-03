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
