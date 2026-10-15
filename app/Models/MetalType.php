<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reference data this module needs but doesn't own — the shop's list of
 * metals (Gold, Silver). See database/seeders/ReferenceDataSeeder.php.
 */
class MetalType extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function purities(): HasMany
    {
        return $this->hasMany(Purity::class);
    }
}
