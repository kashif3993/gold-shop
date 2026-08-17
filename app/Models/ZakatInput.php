<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZakatInput extends Model
{
    const CREATED_AT = null;

    protected $fillable = [
        'cash_in_hand',
        'liabilities_owed',
        'nisab_gold_grams',
        'nisab_silver_grams',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'cash_in_hand' => 'decimal:2',
            'liabilities_owed' => 'decimal:2',
            'nisab_gold_grams' => 'decimal:3',
            'nisab_silver_grams' => 'decimal:3',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
