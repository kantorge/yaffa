<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\ModelOwnedByUserTrait;
use App\Services\RecurrenceRuleService;
use Brick\Money\Money;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Budget
 *
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property int|null $account_id
 * @property TransactionTypeEnum $transaction_type
 * @property-read Money $amount
 * @property-write Money|string|int|float $amount
 * @property string|null $comment
 * @property string $frequency
 * @property int $interval
 * @property string|null $by_day
 * @property int|null $by_month
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int|null $count
 * @property float|null $inflation
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccountEntity|null $account
 * @property-read Category $category
 * @property-read User $user
 * @method static BudgetFactory factory(...$parameters)
 * @method static Builder|Budget newModelQuery()
 * @method static Builder|Budget newQuery()
 * @method static Builder|Budget query()
 * @method static Builder|Budget whereAccountId($value)
 * @method static Builder|Budget whereActive($value)
 * @method static Builder|Budget whereAmount($value)
 * @method static Builder|Budget whereCategoryId($value)
 * @method static Builder|Budget whereComment($value)
 * @method static Builder|Budget whereCount($value)
 * @method static Builder|Budget whereCreatedAt($value)
 * @method static Builder|Budget whereEndDate($value)
 * @method static Builder|Budget whereFrequency($value)
 * @method static Builder|Budget whereId($value)
 * @method static Builder|Budget whereInflation($value)
 * @method static Builder|Budget whereInterval($value)
 * @method static Builder|Budget whereStartDate($value)
 * @method static Builder|Budget whereUpdatedAt($value)
 * @method static Builder|Budget whereUserId($value)
 * @mixin Eloquent
 */
#[Fillable('category_id', 'account_id', 'transaction_type', 'amount', 'comment', 'frequency', 'interval', 'by_day', 'by_month', 'start_date', 'end_date', 'count', 'inflation')]
class Budget extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionTypeEnum::class,
            'amount' => MoneyCast::class . ':4,currency',
            'start_date' => 'date',
            'end_date' => 'date',
            'count' => 'integer',
            'interval' => 'integer',
            'by_month' => 'integer',
            'inflation' => 'float',
            'active' => 'boolean',
        ];
    }

    // Define closures for creating and updating a budget, so that the active flag can be set
    protected static function booted(): void
    {
        static::creating(function (Budget $budget) {
            $budget->active = $budget->isActive();
        });

        static::updating(function (Budget $budget) {
            $budget->active = $budget->isActive();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_id');
    }

    /**
     * The budget's effective currency: its linked account's current currency, or the user's
     * base currency when the budget is account-agnostic. Never stored, always derived, so it
     * can never drift out of sync with the account it's attached to.
     */
    public function currency(): ?Currency
    {
        if ($this->account_id) {
            $config = $this->account?->config;

            return $config instanceof Account ? $config->currency : null;
        }

        return $this->user->baseCurrency();
    }

    /**
     * Determine if the budget is considered to be active.
     *
     * Unlike a schedule, a budget has no next_date shortcut: its active flag is always derived
     * from whether its recurrence rule yields at least one occurrence on or after today.
     */
    public function isActive(): bool
    {
        return (new RecurrenceRuleService())->hasOccurrenceOnOrAfter(
            $this->start_date,
            $this->frequency,
            $this->interval ?? 1,
            $this->end_date,
            $this->count,
            $this->by_day,
            $this->by_month,
            Carbon::now(),
        );
    }
}
