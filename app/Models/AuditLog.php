<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    const CREATED_AT = 'changed_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'changed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
