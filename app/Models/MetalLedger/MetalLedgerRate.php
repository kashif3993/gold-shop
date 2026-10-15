<?php

declare(strict_types=1);

namespace App\Models\MetalLedger;

use App\Models\MetalType;
use App\Models\Purity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row in the Metal Ledger rate book (Task 2) — a rate for one metal, one
 * purity, one buy/sell type, in force from `effective_at`. Rows are never
 * updated or deleted by the service that writes them; a correction is
 * always a new row, so this table is a full, permanent history.
 */
class MetalLedgerRate extends Model
{
    public $timestamps = false;

    protected $table = 'metal_ledger_rates';

    protected $fillable = [
        'metal_type_id',
        'purity_id',
        'rate_type',
        'rate_per_gram',
        'effective_at',
        'source',
        'entered_by_user_id',
        'is_current',
        'superseded_by_manual',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_gram' => 'decimal:2',
            'effective_at' => 'datetime',
            'created_at' => 'datetime',
            'is_current' => 'boolean',
            'superseded_by_manual' => 'boolean',
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
