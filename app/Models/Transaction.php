<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'party_id',
        'transaction_type_id',
        'direction',
        'item_id',
        'metal_type_id',
        'purity_id',
        'weight_grams',
        'rate_per_gram',
        'metal_cost',
        'labour_cost',
        'polish_cost',
        'tax_amount',
        'discount_amount',
        'discount_reason',
        'deduction_percent_applied',
        'total_amount',
        'original_sale_transaction_id',
        'invoice_id',
        'payment_method',
        'transaction_date',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'metal_cost' => 'decimal:2',
            'labour_cost' => 'decimal:2',
            'polish_cost' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'deduction_percent_applied' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    /**
     * The original sale this row buys back — reference-only, never used to derive value.
     */
    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_sale_transaction_id');
    }

    public function buyBacks(): HasMany
    {
        return $this->hasMany(Transaction::class, 'original_sale_transaction_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function invoiceLineItem(): HasOne
    {
        return $this->hasOne(InvoiceLineItem::class);
    }
}
