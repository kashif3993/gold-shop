<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'item_id',
        'transaction_id',
        'weight_grams',
        'purity_id',
        'rate_per_gram',
        'metal_cost',
        'labour_cost',
        'polish_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'metal_cost' => 'decimal:2',
            'labour_cost' => 'decimal:2',
            'polish_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }
}
