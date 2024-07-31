<?php

namespace App\Models;

use App\Enums\RequisitionStatus as Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GocardlessRequisition extends Model
{
    use HasUuids;
    use HasFactory;
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'status',
        'institution_id',
        'institution_name',
        'authorization_url',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'gocardless_created_at' => 'datetime',
        'status' => Status::class,
    ];

    protected $appends = [
        'status_label'
    ];

    public function getStatusLabelAttribute(): string
    {
        return $this->status->getLabel();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allAccounts(): HasMany
    {
        return $this->hasMany(GocardlessAccount::class, 'requisition_id');
    }

    public function linkedAccounts(): HasMany
    {
        return $this->allAccounts()
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('accounts')
                    ->whereColumn('gocardless_account_id', 'gocardless_accounts.id');
            });
    }
}
