<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purity extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'metal_type_id',
        'name',
        'fineness_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fineness_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function dailyRates(): HasMany
    {
        return $this->hasMany(DailyRate::class);
    }

    public function invoiceLineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function buybackDeductionSettings(): HasMany
    {
        return $this->hasMany(BuybackDeductionSetting::class);
    }

    public function rateAdjustmentSettings(): HasMany
    {
        return $this->hasMany(RateAdjustmentSetting::class);
    }
}
