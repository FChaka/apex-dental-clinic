<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientMedicalHistory;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use App\Support\PatientArraySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientMedicalHistoryController extends Controller
{
    use InteractsWithClinicPatient;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function show(Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $patient->loadMissing('medicalHistory');

        return JsonApiResponse::success(
            PatientArraySerializer::medicalHistory($patient->medicalHistory),
            'OK'
        );
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $validated = $request->validate([
            'allergies' => ['sometimes', 'array'],
            'conditions' => ['sometimes', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $row = PatientMedicalHistory::query()->firstOrNew(['patient_id' => $patient->id]);
        if (array_key_exists('allergies', $validated)) {
            $row->allergies = $validated['allergies'];
        } elseif (! $row->exists) {
            $row->allergies = [];
        }
        if (array_key_exists('conditions', $validated)) {
            $row->conditions = $validated['conditions'];
        } elseif (! $row->exists) {
            $row->conditions = [];
        }
        if (array_key_exists('notes', $validated)) {
            $row->notes = $validated['notes'];
        }
        $row->save();

        return JsonApiResponse::success(
            PatientArraySerializer::medicalHistory($row),
            'Medical history updated successfully.'
        );
    }
}
