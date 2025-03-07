<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\ModelFlags\Models\Flag;
use Spatie\Onboard\Concerns\GetsOnboarded;
use Spatie\Onboard\Concerns\Onboardable;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string $language
 * @property string $locale
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AccountGroup> $accountGroups
 * @property-read int|null $account_groups_count
 * @property-read Collection<int, AccountEntity> $accounts
 * @property-read int|null $accounts_count
 * @property-read Collection<int, Category> $categories
 * @property-read int|null $categories_count
 * @property-read Collection<int, Currency> $currencies
 * @property-read int|null $currencies_count
 * @property-read Collection<int, Flag> $flags
 * @property-read int|null $flags_count
 * @property-read Collection<int, InvestmentGroup> $investmentGroups
 * @property-read int|null $investment_groups_count
 * @property-read Collection<int, Investment> $investments
 * @property-read int|null $investments_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, AccountEntity> $payees
 * @property-read int|null $payees_count
 * @property-read Collection<int, Tag> $tag
 * @property-read int|null $tags_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder|User flagged(string $name)
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User notFlagged(string $name)
 * @method static Builder|User query()
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereEmailVerifiedAt($value)
 * @method static Builder|User whereEndDate($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereLanguage($value)
 * @method static Builder|User whereLocale($value)
 * @method static Builder|User whereName($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereStartDate($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @mixin Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail, Onboardable
{
    use GetsOnboarded;
    use HasApiTokens;
    use HasFactory;
    use HasFlags;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'language',
        'locale',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected $withCount = [
        'unhandledReceivedMail',
    ];

    public function accountGroups(): HasMany
    {
        return $this->hasMany(AccountGroup::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(AccountEntity::class)->accounts();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class);
    }

    public function baseCurrency(): Currency|null
    {
        return $this->currencies()
            ->where('base', true)
            ->firstOr(fn () => null);
    }

    public function investmentGroups(): HasMany
    {
        return $this->hasMany(InvestmentGroup::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function payees(): HasMany
    {
        return $this->hasMany(AccountEntity::class)->payees();
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionCount(): int
    {
        return $this->hasMany(Transaction::class)->count();
    }

    public function receivedMails(): HasMany
    {
        return $this->hasMany(ReceivedMail::class);
    }

    public function unhandledReceivedMail(): HasMany
    {
        return $this->hasMany(ReceivedMail::class)->unhandled();
    }
}
