<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $hidden = ['transaction_id'];

    protected $casts = [
        'next_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }

    public function getNextInstance() {

        $constraint = new \Recurr\Transformer\Constraint\AfterConstraint(new \DateTime ($this->next_date), false);
        $rule = new \Recurr\Rule();

        $rule->setStartDate(new \DateTime($this->start_date));

        if ($this->end_date) {
            $rule->setUntil(new \DateTime($this->end_date));
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

        $recurrence = $transformer->transform($rule,$constraint);

        if (empty($recurrence)) {
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
