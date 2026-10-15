<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purity master data (Task 1, FR-1) — reference data this module needs but
 * doesn't own. See database/seeders/ReferenceDataSeeder.php.
 */
class Purity extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'metal_type_id',
        'name',
        'fineness_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fineness_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }
}
