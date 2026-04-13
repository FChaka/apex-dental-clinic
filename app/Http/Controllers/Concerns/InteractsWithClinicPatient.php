<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait InteractsWithClinicPatient
{
    protected function clinicStaff(): StaffMember|JsonResponse
    {
        $staff = Auth::guard('clinic_session')->user();
        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return $staff;
    }

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
