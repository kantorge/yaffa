<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ai_document_id
 * @property string $file_path
 * @property string $file_name
 * @property string $file_type
 * @property-read AiDocument $aiDocument
 * @method static \Database\Factories\AiDocumentFileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereAiDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereFileType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereId($value)
 * @mixin \Eloquent
 */
class AiDocumentFile extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ai_document_id',
        'file_path',
        'file_name',
        'file_type',
    ];

    public function aiDocument(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class);
    }
}
