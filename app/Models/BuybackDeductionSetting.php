<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuybackDeductionSetting extends Model
{
    const CREATED_AT = null;

    protected $fillable = [
        'metal_type_id',
        'purity_id',
        'deduction_percent',
        'is_shop_default',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'deduction_percent' => 'decimal:2',
            'is_shop_default' => 'boolean',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
