<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    protected $table = 'import_jobs';

    protected $fillable = [
        'user_id',
        'account_entity_id',
        'file_path',
        'source',
        'status',
        'total_rows',
        'processed_rows',
        'errors',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function accountEntity()
    {
        return $this->belongsTo(\App\Models\AccountEntity::class);
    }
}
