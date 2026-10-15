<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task 3, FR-16 — new party types are an owner-level data entry; the
 * ledger itself never special-cases one. See
 * database/seeders/ReferenceDataSeeder.php.
 */
class PartyType extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }
}
