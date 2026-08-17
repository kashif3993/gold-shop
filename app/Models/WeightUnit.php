<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeightUnit extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'grams_per_unit'];

    protected function casts(): array
    {
        return [
            'grams_per_unit' => 'decimal:6',
        ];
    }
}
