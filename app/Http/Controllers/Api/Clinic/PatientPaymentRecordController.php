<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPaymentRecord;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class PatientPaymentRecordController extends Controller
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

        $items = PatientPaymentRecord::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PatientPaymentRecord $p) => $this->serializePayment($p))
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
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'transfer', 'other'])],
            'note' => ['nullable', 'string'],
            'treatment_id' => [
                'nullable',
                'integer',
                Rule::exists('patient_treatment_entries', 'id')->where('patient_id', $patient->id),
            ],
            'treatment_label' => ['nullable', 'string', 'max:255'],
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('invoices', 'id')->where('patient_id', $patient->id),
            ],
            'monthly_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('patient_monthly_plans', 'id')->where('patient_id', $patient->id),
            ],
            'is_monthly_plan_payment' => ['sometimes', 'boolean'],
            'source' => ['sometimes', Rule::in(['treatment', 'manual'])],
        ]);

        $payment = DB::transaction(function () use ($validated, $patient) {
            $payment = PatientPaymentRecord::query()->create([
                ...$validated,
                'patient_id' => $patient->id,
            ]);

            if (! empty($validated['treatment_id'])) {
                /** @var PatientTreatmentEntry $entry */
                $entry = PatientTreatmentEntry::query()
                    ->whereKey($validated['treatment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $entry->amount_paid = (string) round(
                    (float) $entry->amount_paid + (float) $validated['amount'],
                    2
                );
                $entry->syncPaymentStatusFromAmounts();
                $entry->save();
            }

            return $payment;
        });

        return JsonApiResponse::success($this->serializePayment($payment), 'Payment recorded successfully.', Response::HTTP_CREATED);
    }

    public function destroy(Patient $patient, PatientPaymentRecord $payment): JsonResponse|Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $payment->patient_id !== (int) $patient->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($payment) {
            if ($payment->treatment_id !== null) {
                /** @var PatientTreatmentEntry $entry */
                $entry = PatientTreatmentEntry::query()
                    ->whereKey($payment->treatment_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newPaid = round((float) $entry->amount_paid - (float) $payment->amount, 2);
                $entry->amount_paid = (string) max(0, $newPaid);
                $entry->syncPaymentStatusFromAmounts();
                $entry->save();
            }

            $payment->delete();
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(PatientPaymentRecord $p): array
    {
        return [
            'id' => $p->id,
            'patient_id' => $p->patient_id,
            'date' => $p->date instanceof CarbonInterface ? $p->date->format('Y-m-d') : (string) $p->date,
            'amount' => $p->amount,
            'method' => $p->method,
            'note' => $p->note,
            'treatment_id' => $p->treatment_id,
            'treatment_label' => $p->treatment_label,
            'invoice_id' => $p->invoice_id,
            'monthly_plan_id' => $p->monthly_plan_id,
            'is_monthly_plan_payment' => $p->is_monthly_plan_payment,
            'source' => $p->source,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
