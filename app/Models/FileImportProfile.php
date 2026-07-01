<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * @property array<string, mixed>|null $mapping_json
 * @property array<string, mixed>|null $options_json
 */
class FileImportProfile extends Model
{
    /** @use HasFactory<\Database\Factories\FileImportProfileFactory> */
    use HasFactory;

    protected $table = 'file_import_profiles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_type',
        'name',
        'delimiter',
        'has_header_row',
        'date_format',
        'decimal_separator',
        'thousand_separator',
        'sign_handling',
        'mapping_json',
        'options_json',
        'active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if ($profile->type === 'system' && blank($profile->key)) {
                throw ValidationException::withMessages([
                    'key' => __('System file import profiles require a stable key.'),
                ]);
            }

            if ($profile->type === 'user' && blank($profile->user_id)) {
                throw ValidationException::withMessages([
                    'user_id' => __('User file import profiles must belong to a user.'),
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'has_header_row' => 'boolean',
            'mapping_json' => 'array',
            'options_json' => 'array',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountEntities(): HasMany
    {
        return $this->hasMany(AccountEntity::class, 'preferred_file_import_profile_id');
    }

    #[Scope]
    protected function selectableForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('type', 'system')
                ->orWhere(function (Builder $userBuilder) use ($user): void {
                    $userBuilder->where('type', 'user')
                        ->where('user_id', $user->id);
                });
        });
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function isUserOwnedBy(User $user): bool
    {
        return $this->type === 'user' && (int) $this->user_id === (int) $user->id;
    }

}
