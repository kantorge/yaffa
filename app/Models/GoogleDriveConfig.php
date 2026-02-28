<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $service_account_email
 * @property string $service_account_json
 * @property string $folder_id
 * @property bool $delete_after_import
 * @property bool $enabled
 * @property \Illuminate\Support\Carbon|null $last_sync_at
 * @property string|null $last_error
 * @property int $error_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\GoogleDriveConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereDeleteAfterImport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereErrorCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereFolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereLastSyncAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereServiceAccountEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereServiceAccountJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleDriveConfig whereUserId($value)
 * @mixin \Eloquent
 */
class GoogleDriveConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $hidden = [
        'service_account_json',
    ];

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
