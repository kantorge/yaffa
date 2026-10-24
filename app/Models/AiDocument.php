<?php

namespace App\Models;

use App\Enums\AiDocumentSource;
use App\Enums\AiDocumentStatus;
use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string $source_type
 * @property array<array-key, mixed>|null $processed_transaction_data
 * @property array<array-key, mixed>|null $ai_chat_history
 * @property string|null $google_drive_file_id
 * @property int|null $received_mail_id
 * @property string|null $custom_prompt
 * @property string|null $content_hash
 * @property string|null $document_kind
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
#[Fillable('status', 'source_type', 'processed_transaction_data', 'ai_chat_history', 'google_drive_file_id', 'received_mail_id', 'custom_prompt', 'content_hash', 'document_kind', 'processed_at')]
class AiDocument extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected function casts(): array
    {
        return [
            'processed_transaction_data' => 'array',
            'ai_chat_history' => 'array',
            'processed_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->status_changed_at ??= now();
        });

        // The `created` origin of a transaction keeps its provenance and review state without the document;
        // the rows that only link the document to a transaction are meaningless without it.
        static::deleting(function (self $document): void {
            $document->origins()->where('relation', '!=', TransactionOrigin::RELATION_CREATED)->delete();
            $document->origins()->update(['origin_id' => null]);
        });
    }

    /**
     * The only place a document's status changes after creation: records when it changed (the itemization
     * timeout is measured from it) and saves the document together with any other pending attribute changes.
     */
    public function transitionTo(AiDocumentStatus $status): void
    {
        $this->status = $status->value;
        $this->status_changed_at = now();
        $this->save();
    }

    /**
     * sha256 of the sorted sha256 values of the document's files, so the same content always hashes the same.
     *
     * @param  list<string>  $fileHashes
     */
    public static function hashFiles(array $fileHashes): string
    {
        sort($fileHashes);

        return hash('sha256', implode('', $fileHashes));
    }

    /**
     * A receipt or an invoice with at least one line item: the only kind of document whose items can be trusted
     * to be a full breakdown of the purchase.
     */
    public function isItemized(): bool
    {
        $draft = $this->processed_transaction_data ?? [];
        $items = $draft['transaction_items'] ?? $draft['raw']['transaction_items'] ?? [];

        return in_array($this->document_kind, ['receipt', 'invoice'], true) && is_array($items) && $items !== [];
    }

    public function origins(): MorphMany
    {
        return $this->morphMany(TransactionOrigin::class, 'origin');
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

    /**
     * Documents untouched since the cutoff: both created_at and updated_at are older than it.
     */
    #[Scope]
    protected function olderThan(Builder $query, Carbon $cutoff): Builder
    {
        return $query->where('created_at', '<', $cutoff)->where('updated_at', '<', $cutoff);
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
