<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'source_type',
        'processed_transaction_data',
        'google_drive_file_id',
        'received_mail_id',
        'custom_prompt',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_transaction_data' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AiDocumentFile::class);
    }

    public function receivedMail(): BelongsTo
    {
        return $this->belongsTo(ReceivedMail::class);
    }
}
