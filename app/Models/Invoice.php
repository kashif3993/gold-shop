<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_number',
        'party_id',
        'invoice_date',
        'total_metal_cost',
        'total_labour_cost',
        'total_polish_cost',
        'total_tax',
        'total_discount',
        'total_exchange_deduction',
        'grand_total',
        'payment_method',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'total_metal_cost' => 'decimal:2',
            'total_labour_cost' => 'decimal:2',
            'total_polish_cost' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_exchange_deduction' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
