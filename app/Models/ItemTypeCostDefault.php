<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemTypeCostDefault extends Model
{
    const CREATED_AT = null;

    protected $fillable = [
        'item_type',
        'labour_cost_default',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'labour_cost_default' => 'decimal:2',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
