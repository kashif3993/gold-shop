<?php

declare(strict_types=1);

namespace App\Models\MetalLedger;

use App\Models\MetalType;
use App\Models\Purity;
use App\Models\User;
use App\Services\MetalLedger\BuyBackValuationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One deduction-percent rule for Task 5's buy-back valuation — resolved by
 * {@see BuyBackValuationService::resolveDeductionPercent()}
 * most-specific first: an exact metal+purity row, then a metal-wide row,
 * then the shop-wide default.
 */
class MetalLedgerBuyBackDeduction extends Model
{
    protected $table = 'metal_ledger_buyback_deductions';

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
