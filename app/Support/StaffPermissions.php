<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\StaffMember;

/**
 * v1 permission strings derived from role and clinic access level (policy parity deferred in blueprint §13).
 */
final class StaffPermissions
{
    /**
     * @return list<string>
     */
    public static function forStaff(StaffMember $staff): array
    {
        $level = $staff->clinic_access_level;
        $role = $staff->role;

        $base = [
            'clinic.access:'.$level,
            'staff.role:'.$role,
        ];

        return match ($level) {
            'super_admin' => array_merge($base, [
                'staff.manage',
                'settings.manage',
                'billing.manage',
                'patients.manage',
                'appointments.manage',
            ]),
            'admin' => array_merge($base, [
                'settings.read',
                'staff.read',
                'staff.write',
                'patients.manage',
                'appointments.manage',
                'billing.read',
            ]),
            default => array_merge($base, [
                'patients.read',
                'appointments.read',
            ]),
        };
    }
}
