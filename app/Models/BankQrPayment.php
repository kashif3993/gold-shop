<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BankQrPayment extends Model
{
    protected $fillable = [
        'reference',
        'amount',
        'cart_snapshot',
        'status',
        'bank_txn_id',
        'invoice_id',
        'created_by_user_id',
        'confirmed_by_user_id',
        'expires_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cart_snapshot' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /** Past its window and never confirmed — permanently unconfirmable, regardless of the stored status. */
    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->status === 'pending' && Carbon::parse($this->expires_at)->isPast());
    }
}
