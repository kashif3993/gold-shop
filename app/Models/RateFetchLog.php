<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateFetchLog extends Model
{
    protected $table = 'rate_fetch_log';

    const CREATED_AT = 'attempted_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'success',
        'source_api',
        'response_summary',
        'fallback_used',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'fallback_used' => 'boolean',
        ];
    }
}
