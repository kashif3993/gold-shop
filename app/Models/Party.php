<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer, karigar, wholesaler, other shop or company — reference data
 * this module needs but doesn't own. See
 * database/seeders/ReferenceDataSeeder.php.
 */
class Party extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'party_type_id',
        'name',
        'phone',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function partyType(): BelongsTo
    {
        return $this->belongsTo(PartyType::class);
    }
}
