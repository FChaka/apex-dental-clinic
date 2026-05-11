<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Services\DataScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

trait InteractsWithClinicPatient
{
    use InteractsWithClinicStaff;

    protected function guardPatientAccess(DataScopeService $dataScope, StaffMember $staff, Patient $patient): ?JsonResponse
    {
        try {
            $dataScope->ensurePatientAccess($staff, $patient);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return null;
    }
}
