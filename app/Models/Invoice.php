<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'discount_reason',
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

    /** Only set for a Bank QR sale — see BankQrPayment::invoice_id, assigned on confirm(). */
    public function bankQrPayment(): HasOne
    {
        return $this->hasOne(BankQrPayment::class);
    }

    /** Every payment recorded against this invoice, oldest first — a full history, never a single overwritten total. */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function amountPaid(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    public function amountDue(): float
    {
        return max(0, round((float) $this->grand_total - $this->amountPaid(), 2));
    }

    /** 'paid' | 'partial' | 'due' — 'due' also covers zero payments made so far. */
    public function paymentStatus(): string
    {
        $due = $this->amountDue();

        if ($due <= 0) {
            return 'paid';
        }

        return $this->amountPaid() > 0 ? 'partial' : 'due';
    }
}
