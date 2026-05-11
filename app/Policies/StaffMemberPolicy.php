<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\StaffMember;

/**
 * Mirrors roster visibility described in PROJECT_BLUEPRINT §7.5.1: practice admins always;
 * anyone may load self; peers may load Active / On Leave colleagues; otherwise deny (e.g. Off Duty peers).
 */
final class StaffMemberPolicy
{
    public function view(?StaffMember $actor, StaffMember $target): bool
    {
        if ($actor === null) {
            return false;
        }

        if (in_array($actor->clinic_access_level, ['super_admin', 'admin'], true)) {
            return true;
        }

        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        return in_array((string) $target->status, ['Active', 'On Leave'], true);
    }
}
