<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $fillable = [
        'provider',
        'model',
        'api_key',
        'vision_enabled',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'vision_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
