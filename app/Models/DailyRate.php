<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRate extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'metal_type_id',
        'purity_id',
        'api_raw_rate_per_gram',
        'adjustment_type_used',
        'adjustment_value_used',
        'rate_per_gram',
        'rate_date',
        'source',
        'fetched_at',
        'is_current',
        'is_stale',
        'entered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'api_raw_rate_per_gram' => 'decimal:2',
            'adjustment_value_used' => 'decimal:4',
            'rate_per_gram' => 'decimal:2',
            'rate_date' => 'date',
            'fetched_at' => 'datetime',
            'is_current' => 'boolean',
            'is_stale' => 'boolean',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }
}
