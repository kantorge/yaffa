<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\InvestmentGroup
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\InvestmentGroupFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereUserId($value)
 *
 * @mixin \Eloquent
 */
class InvestmentGroup extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'investment_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
