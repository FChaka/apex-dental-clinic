<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\InvoiceTreatmentEntry;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class PatientTreatmentEntryController extends Controller
{
    use InteractsWithClinicPatient;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $items = PatientTreatmentEntry::query()
            ->where('patient_id', $patient->id)
            ->with([
                'treatmentType' => fn ($q) => $q->select('id', 'name', 'default_price'),
                'dentist' => fn ($q) => $q->select('id', 'name'),
            ])
            ->orderByDesc('date')
            ->get()
            ->map(fn (PatientTreatmentEntry $e) => $this->serializeEntry($e))
            ->values()
            ->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $validated = $request->validate([
            'treatment_type_id' => ['required', 'integer', 'exists:treatment_types,id'],
            'dentist_id' => ['required', 'integer', 'exists:staff_members,id'],
            'date' => ['required', 'date'],
            'tooth_number' => ['nullable', 'string', 'max:10'],
            'price' => ['required', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(['Paid', 'Pending'])],
        ]);

        $entry = new PatientTreatmentEntry($validated);
        $entry->patient_id = $patient->id;
        if (! isset($validated['amount_paid'])) {
            $entry->amount_paid = 0;
        }
        $entry->syncPaymentStatusFromAmounts();
        $entry->save();

        $entry->load([
            'treatmentType' => fn ($q) => $q->select('id', 'name', 'default_price'),
            'dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        return JsonApiResponse::success($this->serializeEntry($entry), 'Treatment entry created successfully.', Response::HTTP_CREATED);
    }

    public function update(Request $request, Patient $patient, PatientTreatmentEntry $entry): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $entry->patient_id !== (int) $patient->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'treatment_type_id' => ['sometimes', 'integer', 'exists:treatment_types,id'],
            'dentist_id' => ['sometimes', 'integer', 'exists:staff_members,id'],
            'date' => ['sometimes', 'date'],
            'tooth_number' => ['nullable', 'string', 'max:10'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(['Paid', 'Pending'])],
        ]);

        $entry->fill($validated);
        $entry->syncPaymentStatusFromAmounts();
        $entry->save();

        $entry->load([
            'treatmentType' => fn ($q) => $q->select('id', 'name', 'default_price'),
            'dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        return JsonApiResponse::success($this->serializeEntry($entry), 'Treatment entry updated successfully.');
    }

    public function destroy(Patient $patient, PatientTreatmentEntry $entry): JsonResponse|Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $entry->patient_id !== (int) $patient->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (InvoiceTreatmentEntry::query()->where('treatment_entry_id', $entry->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a treatment entry that is linked to an invoice.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entry->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(PatientTreatmentEntry $e): array
    {
        return [
            'id' => $e->id,
            'patient_id' => $e->patient_id,
            'treatment_type_id' => $e->treatment_type_id,
            'dentist_id' => $e->dentist_id,
            'date' => $e->date instanceof CarbonInterface ? $e->date->format('Y-m-d') : (string) $e->date,
            'tooth_number' => $e->tooth_number,
            'price' => $e->price,
            'amount_paid' => $e->amount_paid,
            'payment_status' => $e->payment_status,
            'treatment_type' => $e->relationLoaded('treatmentType') && $e->treatmentType !== null ? [
                'id' => $e->treatmentType->id,
                'name' => $e->treatmentType->name,
                'default_price' => $e->treatmentType->default_price,
            ] : null,
            'dentist' => $e->relationLoaded('dentist') && $e->dentist !== null ? [
                'id' => $e->dentist->id,
                'name' => $e->dentist->name,
            ] : null,
        ];
    }
}
