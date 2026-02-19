<?php

namespace App\Models;

use App\Enums\AiDocumentSource;
use App\Enums\AiDocumentStatus;
use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string $source_type
 * @property array<array-key, mixed>|null $processed_transaction_data
 * @property string|null $google_drive_file_id
 * @property int|null $received_mail_id
 * @property string|null $custom_prompt
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiDocumentFile> $aiDocumentFiles
 * @property-read int|null $ai_document_files_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiDocumentFile> $files
 * @property-read int|null $files_count
 * @property-read ReceivedMail|null $receivedMail
 * @property-read Transaction|null $transaction
 * @property-read User $user
 * @method static \Database\Factories\AiDocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereCustomPrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereGoogleDriveFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereProcessedTransactionData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereReceivedMailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereUserId($value)
 * @mixin \Eloquent
 */
class AiDocument extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $fillable = [
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

    public function aiDocumentFiles(): HasMany
    {
        return $this->hasMany(AiDocumentFile::class);
    }

    public function receivedMail(): BelongsTo
    {
        return $this->belongsTo(ReceivedMail::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public static function statusLabels(): array
    {
        return AiDocumentStatus::labels();
    }

    public static function sourceLabels(): array
    {
        return AiDocumentSource::labels();
    }
}
