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
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
