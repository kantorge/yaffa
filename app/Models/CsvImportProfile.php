<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CsvImportProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'type',
        'name',
        'delimiter',
        'has_header_row',
        'date_format',
        'decimal_separator',
        'thousand_separator',
        'sign_handling',
        'mapping_json',
        'options_json',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'has_header_row' => 'boolean',
            'mapping_json' => 'array',
            'options_json' => 'array',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
