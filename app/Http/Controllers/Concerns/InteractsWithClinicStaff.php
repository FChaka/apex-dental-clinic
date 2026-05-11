<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Tenant\StaffMember;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait InteractsWithClinicStaff
{
    protected function clinicStaff(): StaffMember|JsonResponse
    {
        $staff = Auth::guard('clinic_session')->user();
        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return $staff;
    }
}
