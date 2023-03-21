<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTime;

/**
 * App\Models\TransactionSchedule
 *
 * @property int $id
 * @property int $transaction_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $next_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string $frequency
 * @property int $interval
 * @property int|null $count
 * @property float|null $inflation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Transaction $transaction
 * @method static \Database\Factories\TransactionScheduleFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereInflation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereNextDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionSchedule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TransactionSchedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id',
        'start_date',
        'next_date',
        'end_date',
        'frequency',
        'count',
        'interval',
        'inflation',
    ];

    protected $hidden = ['transaction_id'];

    protected $casts = [
        'next_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getNextInstance()
    {
        $constraint = new \Recurr\Transformer\Constraint\AfterConstraint(new DateTime($this->next_date), false);
        $rule = new \Recurr\Rule();

        $rule->setStartDate(new DateTime($this->start_date));

        if ($this->end_date) {
            $rule->setUntil(new DateTime($this->end_date));
        }

        $rule->setFreq($this->frequency);

        if ($this->count) {
            $rule->setCount($this->count);
        }
        if ($this->interval) {
            $rule->setInterval($this->interval);
        }

        $transformer = new \Recurr\Transformer\ArrayTransformer();

        $transformerConfig = new \Recurr\Transformer\ArrayTransformerConfig();

        $transformerConfig->enableLastDayOfMonthFix();
        $transformer->setConfig($transformerConfig);

        $recurrence = $transformer->transform($rule, $constraint);

        if ($recurrence->count() === 0) {
            return null;
        }

        return $recurrence[0]->getStart();
    }

    public function skipNextInstance()
    {
        $this->next_date = $this->getNextInstance();

        return $this->save();
    }
}
