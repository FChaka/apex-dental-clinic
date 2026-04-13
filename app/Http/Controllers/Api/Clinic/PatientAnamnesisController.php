<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientAnamnesis;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use App\Support\PatientArraySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientAnamnesisController extends Controller
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

        $patient->loadMissing('anamnesis');

        return JsonApiResponse::success(
            PatientArraySerializer::anamnesis($patient->anamnesis),
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
            'chief_complaint' => ['sometimes', 'nullable', 'string'],
            'present_illness' => ['sometimes', 'nullable', 'string'],
            'current_medications' => ['sometimes', 'nullable', 'string'],
            'previous_surgeries' => ['sometimes', 'nullable', 'string'],
            'family_history' => ['sometimes', 'nullable', 'string'],
            'dental_history' => ['sometimes', 'nullable', 'string'],
            'other' => ['sometimes', 'nullable', 'string'],
        ]);

        $fields = [
            'chief_complaint', 'present_illness', 'current_medications', 'previous_surgeries',
            'family_history', 'dental_history', 'other',
        ];

        $row = PatientAnamnesis::query()->firstOrNew(['patient_id' => $patient->id]);
        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $row->{$field} = $validated[$field];
            }
        }
        $row->save();

        return JsonApiResponse::success(
            PatientArraySerializer::anamnesis($row),
            'Anamnesis updated successfully.'
        );
    }
}
