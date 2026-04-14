<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CsvImportProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CsvImportProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'type',
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
                    'key' => __('System CSV import profiles require a stable key.'),
                ]);
            }

            if ($profile->type === 'user' && blank($profile->user_id)) {
                throw ValidationException::withMessages([
                    'user_id' => __('User CSV import profiles must belong to a user.'),
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

    /**
     * @return array<string, mixed>
     */
    public function toUserCloneAttributes(User $user, ?string $name = null): array
    {
        return [
            'user_id' => $user->id,
            'key' => null,
            'type' => 'user',
            'name' => $name ?: __(':name (Copy)', ['name' => $this->name]),
            'delimiter' => $this->delimiter,
            'has_header_row' => $this->has_header_row,
            'date_format' => $this->date_format,
            'decimal_separator' => $this->decimal_separator,
            'thousand_separator' => $this->thousand_separator,
            'sign_handling' => $this->sign_handling,
            'mapping_json' => $this->mapping_json ?? [],
            'options_json' => $this->sanitizedOptionsForUserProfile(),
            'active' => $this->active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizedOptionsForUserProfile(): array
    {
        $options = is_array($this->options_json) ? $this->options_json : [];

        unset(
            $options['matching_rules'],
            $options['actions'],
            $options['transform_catalog'],
            $options['defaults']
        );

        return $options;
    }
}
