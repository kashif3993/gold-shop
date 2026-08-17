<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'item_code',
        'qr_payload',
        'item_type',
        'metal_type_id',
        'purity_id',
        'gross_weight_grams',
        'stone_weight_grams',
        'cutting_loss_grams',
        'labour_cost',
        'polish_cost',
        'purchase_rate_per_gram',
        'purchase_price',
        'source_party_id',
        'date_received',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'gross_weight_grams' => 'decimal:3',
            'stone_weight_grams' => 'decimal:3',
            'cutting_loss_grams' => 'decimal:3',
            'net_weight_grams' => 'decimal:3',
            'labour_cost' => 'decimal:2',
            'polish_cost' => 'decimal:2',
            'purchase_rate_per_gram' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'date_received' => 'date',
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

    public function sourceParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'source_party_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoiceLineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
