<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class DataScopeService
{
    public function scopePatients(Builder $query, StaffMember $staff): Builder
    {
        return $query;
    }

    public function scopeAppointments(Builder $query, StaffMember $staff): Builder
    {
        if ($staff->clinic_access_level === 'staff') {
            $query->where('dentist_id', $staff->id);
        }

        return $query;
    }

    public function scopeTreatmentRecords(Builder $query, StaffMember $staff): Builder
    {
        if ($staff->clinic_access_level === 'staff') {
            $query->where('dentist_id', $staff->id);
        }

        return $query;
    }

    public function scopeLeaveRequests(Builder $query, StaffMember $staff): Builder
    {
        if ($staff->clinic_access_level === 'staff') {
            $query->where('staff_id', $staff->id);
        }

        return $query;
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
