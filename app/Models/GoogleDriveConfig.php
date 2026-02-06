<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $fillable = [
        'service_account_email',
        'service_account_json',
        'folder_id',
        'delete_after_import',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'service_account_json' => 'encrypted',
            'delete_after_import' => 'boolean',
            'enabled' => 'boolean',
            'last_sync_at' => 'datetime',
            'error_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
