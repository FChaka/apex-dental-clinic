<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\AuditLog;

final class AuditService
{
    /**
     * Log a platform admin action to the central audit_log table.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function log(string $action, ?int $clinicId = null, ?string $description = null, ?array $metadata = null): void
    {
        AuditLog::query()->create([
            'admin_id' => auth('platform_session')->id(),
            'clinic_id' => $clinicId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
