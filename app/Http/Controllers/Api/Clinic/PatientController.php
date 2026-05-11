<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientAnamnesis;
use App\Models\Tenant\PatientMedicalHistory;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use App\Support\PatientArraySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class PatientController extends Controller
{
    use InteractsWithClinicPatient;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(['Active', 'Inactive'])],
            'has_pending_payments' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $query = Patient::query()
            ;

        $this->dataScope->scopePatients($query, $staff);

        if (! empty($validated['search'])) {
            $term = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('surname', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if ($request->has('has_pending_payments') && $validated['has_pending_payments'] !== null) {
            if ($validated['has_pending_payments']) {
                $query->whereHas('treatmentEntries', function ($q) {
                    $q->where('payment_status', 'Pending');
                });
            } else {
                // No pending treatment lines: either no entries or none still Pending.
                $query->whereDoesntHave('treatmentEntries', function ($q) {
                    $q->where('payment_status', 'Pending');
                });
            }
        }

        $query->orderByDesc('id');

        $paginator = $query->paginate(20);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Patient $p) => PatientArraySerializer::patientListItem($p)
            )
        );

        return JsonApiResponse::paginated($paginator, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $patientRules = $this->patientFieldRules(requireName: true);
        $validated = $request->validate($patientRules);

        $payload = $this->onlyPatientAttributes($validated);

        $patient = DB::transaction(function () use ($payload) {
            $patient = Patient::query()->create($payload);

            PatientMedicalHistory::query()->create([
                'patient_id' => $patient->id,
                'allergies' => [],
                'conditions' => [],
                'notes' => null,
            ]);

            PatientAnamnesis::query()->create([
                'patient_id' => $patient->id,
            ]);

            return $patient;
        });

        $patient->load(['medicalHistory', 'anamnesis']);

        return JsonApiResponse::success(
            $this->patientDetailPayload($patient),
            'Patient created successfully.',
            201
        );
    }

    public function show(Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $patient->load(['medicalHistory', 'anamnesis']);

        return JsonApiResponse::success($this->patientDetailPayload($patient), 'OK');
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

        $patientRules = $this->patientFieldRules(requireName: false);
        $nestedHistory = [
            'medical_history' => ['sometimes', 'array'],
            'medical_history.allergies' => ['sometimes', 'array'],
            'medical_history.conditions' => ['sometimes', 'array'],
            'medical_history.notes' => ['sometimes', 'nullable', 'string'],
        ];
        $nestedAnamnesis = [
            'anamnesis' => ['sometimes', 'array'],
            'anamnesis.chief_complaint' => ['sometimes', 'nullable', 'string'],
            'anamnesis.present_illness' => ['sometimes', 'nullable', 'string'],
            'anamnesis.current_medications' => ['sometimes', 'nullable', 'string'],
            'anamnesis.previous_surgeries' => ['sometimes', 'nullable', 'string'],
            'anamnesis.family_history' => ['sometimes', 'nullable', 'string'],
            'anamnesis.dental_history' => ['sometimes', 'nullable', 'string'],
            'anamnesis.other' => ['sometimes', 'nullable', 'string'],
        ];

        $validated = $request->validate(array_merge($patientRules, $nestedHistory, $nestedAnamnesis));

        DB::transaction(function () use ($patient, $validated) {
            $attrs = $this->onlyPatientAttributes($validated);
            if ($attrs !== []) {
                $patient->update($attrs);
            }

            if (isset($validated['medical_history'])) {
                $mh = $validated['medical_history'];
                PatientMedicalHistory::query()->updateOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'allergies' => $mh['allergies'] ?? [],
                        'conditions' => $mh['conditions'] ?? [],
                        'notes' => $mh['notes'] ?? null,
                    ]
                );
            }

            if (isset($validated['anamnesis'])) {
                $an = $validated['anamnesis'];
                PatientAnamnesis::query()->updateOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'chief_complaint' => $an['chief_complaint'] ?? null,
                        'present_illness' => $an['present_illness'] ?? null,
                        'current_medications' => $an['current_medications'] ?? null,
                        'previous_surgeries' => $an['previous_surgeries'] ?? null,
                        'family_history' => $an['family_history'] ?? null,
                        'dental_history' => $an['dental_history'] ?? null,
                        'other' => $an['other'] ?? null,
                    ]
                );
            }
        });

        $patient->refresh()->load(['medicalHistory', 'anamnesis']);

        return JsonApiResponse::success($this->patientDetailPayload($patient), 'Patient updated successfully.');
    }

    public function destroy(Patient $patient): Response|JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $patient->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, string|object>>
     */
    private function patientFieldRules(bool $requireName): array
    {
        $nameRule = $requireName ? ['required', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'];

        return [
            'name' => $nameRule,
            'surname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fathers_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birthday' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(['Male', 'Female', 'Other'])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'string', 'max:255', 'email'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'personal_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:10'],
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'general_notes' => ['sometimes', 'nullable', 'string'],
            'last_visit' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', Rule::in(['Active', 'Inactive'])],
            'medical_alert' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function onlyPatientAttributes(array $validated): array
    {
        $keys = [
            'name', 'surname', 'fathers_name', 'birthday', 'gender', 'phone', 'email',
            'address', 'city', 'personal_number', 'blood_type', 'avatar_path',
            'general_notes', 'last_visit', 'status', 'medical_alert',
        ];

        return array_intersect_key($validated, array_flip($keys));
    }

    /**
     * @return array<string, mixed>
     */
    private function patientDetailPayload(Patient $patient): array
    {
        $base = PatientArraySerializer::patient($patient);
        $base['medical_history'] = PatientArraySerializer::medicalHistory($patient->medicalHistory);
        $base['anamnesis'] = PatientArraySerializer::anamnesis($patient->anamnesis);

        return $base;
    }
}
