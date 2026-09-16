<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * The single place that writes to audit_log. Every protected-field override
 * (rate edit, price override, weight edit, discount) goes through here so
 * the format — and the fact that it happens at all — never depends on
 * whichever controller happens to touch that field.
 */
class AuditLogger
{
    /** Record a change unconditionally. */
    public function log(string $entityType, int $entityId, string $field, ?string $oldValue, string $newValue, string $reason, ?int $userId = null): AuditLog
    {
        return AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_name' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'changed_by_user_id' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Record a change only if the value actually moved — for diffing several
     * fields on one save (e.g. an item edit that may touch price and/or
     * weight) without writing a row for fields nobody touched.
     */
    public function logIfChanged(string $entityType, int $entityId, string $field, mixed $old, mixed $new, string $reason, ?int $userId = null): ?AuditLog
    {
        // Numeric fields (prices, weights) may arrive with different string
        // formatting on either side (e.g. a decimal-cast "20000.00" vs a raw
        // float 20000.0) — compare those by value, not by exact string match.
        $unchanged = is_numeric($old) && is_numeric($new)
            ? sprintf('%.6f', (float) $old) === sprintf('%.6f', (float) $new)
            : (string) $old === (string) $new;

        if ($unchanged) {
            return null;
        }

        return $this->log($entityType, $entityId, $field, $old === null ? null : (string) $old, (string) $new, $reason, $userId);
    }
}
