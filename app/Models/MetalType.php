<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetalType extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function purities(): HasMany
    {
        return $this->hasMany(Purity::class);
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

    public function buybackDeductionSettings(): HasMany
    {
        return $this->hasMany(BuybackDeductionSetting::class);
    }

    public function rateAdjustmentSettings(): HasMany
    {
        return $this->hasMany(RateAdjustmentSetting::class);
    }
}
