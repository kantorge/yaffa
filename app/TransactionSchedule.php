<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionSchedule extends Model
{
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

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }
}
