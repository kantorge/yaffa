<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentFile extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ai_document_id',
        'file_path',
        'file_name',
        'file_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function aiDocument(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class);
    }
}
