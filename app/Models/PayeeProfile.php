<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Calculated history statistics of one payee, see PayeeProfileService. Amounts are decimal strings in the
 * account currency of the transactions. Everything here is derived and can be rebuilt at any time.
 *
 * @property int $id
 * @property int $account_entity_id
 * @property int $sample_size
 * @property int|null $dominant_category_id
 * @property string $dominant_share
 * @property int $single_item_dominant_count
 * @property string $wilson_lower
 * @property string|null $amount_median
 * @property string|null $amount_min
 * @property string|null $amount_max
 * @property string|null $amount_mode_share
 * @property array<int, string> $known_amounts
 * @property array<int, int> $typical_account_ids
 * @property string $multi_item_share
 * @property Carbon $calculated_at
 * @property-read AccountEntity $payee
 * @property-read Category|null $dominantCategory
 */
#[Table(key: 'id')]
#[WithoutTimestamps]
#[Unguarded]
class PayeeProfile extends Model
{
    protected function casts(): array
    {
        return [
            'known_amounts' => 'array',
            'typical_account_ids' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_entity_id');
    }

    public function dominantCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'dominant_category_id');
    }
}
