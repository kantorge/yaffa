<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider_key
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $options
 * @property bool $enabled
 * @property string|null $last_error
 * @property array<string, int|float>|null $rate_limit_overrides
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\InvestmentProviderConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereCredentials($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereProviderKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereRateLimitOverrides($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvestmentProviderConfig whereUserId($value)
 * @mixin \Eloquent
 */
class InvestmentProviderConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $hidden = [
        'credentials',
    ];

    protected $fillable = [
        'provider_key',
        'credentials',
        'options',
        'enabled',
        'last_error',
        'rate_limit_overrides',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'options' => 'array',
            'enabled' => 'boolean',
            'rate_limit_overrides' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
