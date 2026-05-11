<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class DataScopeService
{
    public function scopePatients(Builder $query, StaffMember $staff): Builder
    {
        return $query;
    }

    public function scopeAppointments(Builder $query, StaffMember $staff): Builder
    {
        if ($this->isCalendarWide($staff)) {
            return $query;
        }

        return $query->where('dentist_id', $staff->id);
    }

    public function scopeTreatmentRecords(Builder $query, StaffMember $staff): Builder
    {
        if ($this->isReportsWide($staff)) {
            return $query;
        }

        return $query->where('dentist_id', $staff->id);
    }

    public function scopeLeaveRequests(Builder $query, StaffMember $staff): Builder
    {
        if ($staff->clinic_access_level === 'staff') {
            $query->where('staff_id', $staff->id);
        }

        return $query;
    }

    public function isPracticeAdmin(StaffMember $user): bool
    {
        return in_array($user->clinic_access_level, ['super_admin', 'admin'], true);
    }

    public function isCalendarWide(StaffMember $user): bool
    {
        if ($this->isPracticeAdmin($user)) {
            return true;
        }

        return in_array($user->role, ['Receptionist', 'Dental Nurse'], true);
    }

    public function isReportsWide(StaffMember $user): bool
    {
        if ($this->isPracticeAdmin($user)) {
            return true;
        }

        return $user->role === 'Receptionist';
    }

    /**
     * @param  'calendar_wide'|'reports_wide'  $gate
     *
     * @throws AuthorizationException
     */
    public function resolveTargetDentistId(StaffMember $user, ?int $requestedDentistId, string $gate): ?int
    {
        $hasWide = match ($gate) {
            'calendar_wide' => $this->isCalendarWide($user),
            'reports_wide' => $this->isReportsWide($user),
            default => throw new InvalidArgumentException('Invalid gate: '.$gate.'.'),
        };

        if ($hasWide) {
            return $requestedDentistId;
        }

        if ($requestedDentistId === null || (int) $requestedDentistId === (int) $user->id) {
            return (int) $user->id;
        }

        throw new AuthorizationException('You are not authorized to scope data to another staff member.');
    }

    public function canAccessPatient(StaffMember $staff, Patient $patient): bool
    {
        return true;
    }

    /**
     * @throws AuthorizationException
     */
    public function ensurePatientAccess(StaffMember $staff, Patient $patient): void
    {
        if (! $this->canAccessPatient($staff, $patient)) {
            throw new AuthorizationException('You do not have access to this patient.');
        }
    }
}
