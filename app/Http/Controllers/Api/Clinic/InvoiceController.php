<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClinicSetting;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceSetting;
use App\Models\Tenant\InvoiceTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Support\JsonApiResponse;
use App\Support\TenantPatientStoragePaths;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InvoiceController extends Controller
{
    private const STATUS_VALUES = ['Paid', 'Pending'];

    public function index(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(self::STATUS_VALUES)],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $query = Invoice::query()
            ->with([
                'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
            ]);

        if (! empty($validated['search'])) {
            $term = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhereHas('patient', function ($pq) use ($term) {
                        $pq->where('name', 'like', $term)
                            ->orWhere('surname', 'like', $term);
                    });
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('date', '<=', $validated['date_to']);
        }

        $query->orderByDesc('date')->orderByDesc('invoice_number');

        $paginator = $query->paginate(20)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Invoice $inv) => $this->serializeInvoiceList($inv))
        );

        return JsonApiResponse::paginated($paginator, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(self::STATUS_VALUES)],
            'treatment_entry_ids' => ['sometimes', 'array'],
            'treatment_entry_ids.*' => [
                'distinct',
                'integer',
                Rule::exists('patient_treatment_entries', 'id')->where('patient_id', $request->input('patient_id')),
            ],
        ]);

        $treatmentEntryIds = $validated['treatment_entry_ids'] ?? [];
        unset($validated['treatment_entry_ids']);

        $invoice = DB::transaction(function () use ($validated, $treatmentEntryIds) {
            $year = Carbon::parse($validated['date'])->year;

            $last = Invoice::query()
                ->whereYear('date', $year)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $seq = 1;
            if ($last !== null && is_string($last->invoice_number) && preg_match('/-(\d{4})$/', $last->invoice_number, $m)) {
                $seq = (int) $m[1] + 1;
            }

            $number = 'INV-'.$year.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::query()->create([
                ...$validated,
                'invoice_number' => $number,
            ]);

            foreach ($treatmentEntryIds as $entryId) {
                InvoiceTreatmentEntry::query()->create([
                    'invoice_id' => $invoice->id,
                    'treatment_entry_id' => $entryId,
                ]);
            }

            return $invoice->fresh();
        });

        $invoice->load([
            'patient',
            'treatmentEntries.treatmentType' => fn ($q) => $q->select('id', 'name'),
            'treatmentEntries.dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        $this->generateAndStoreInvoicePdf($invoice);

        return JsonApiResponse::success($this->serializeInvoiceDetail($invoice), 'Invoice created successfully.', Response::HTTP_CREATED);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $invoice->load([
            'patient',
            'treatmentEntries.treatmentType' => fn ($q) => $q->select('id', 'name'),
            'treatmentEntries.dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        return JsonApiResponse::success($this->serializeInvoiceDetail($invoice), 'OK');
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUS_VALUES)],
            'due_date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice->fill($validated);
        $invoice->save();

        if ($invoice->wasChanged(['amount', 'vat_rate', 'status', 'due_date'])) {
            $this->invalidateStoredInvoicePdf($invoice);

            $invoice->load([
                'patient',
                'treatmentEntries.treatmentType' => fn ($q) => $q->select('id', 'name'),
                'treatmentEntries.dentist' => fn ($q) => $q->select('id', 'name'),
            ]);
            $this->generateAndStoreInvoicePdf($invoice);
        }

        return JsonApiResponse::success($this->serializeInvoiceList($invoice->fresh()->load([
            'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
        ])), 'Invoice updated successfully.');
    }

    public function pdf(Invoice $invoice): JsonResponse|Response|StreamedResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $invoice->load([
            'patient',
            'treatmentEntries.treatmentType' => fn ($q) => $q->select('id', 'name'),
            'treatmentEntries.dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        $disk = config('filesystems.default');
        $storedPath = $invoice->pdf_path;

        if (is_string($storedPath) && $storedPath !== '' && Storage::disk($disk)->exists($storedPath)) {
            return Storage::disk($disk)->download($storedPath, $invoice->invoice_number.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $storedPath = $this->generateAndStoreInvoicePdf($invoice);

        return Storage::disk($disk)->download($storedPath, $invoice->invoice_number.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function generateAndStoreInvoicePdf(Invoice $invoice): string
    {
        $clinicSetting = ClinicSetting::query()->first();
        $invoiceSetting = InvoiceSetting::query()->first();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'patient' => $invoice->patient,
            'clinicSetting' => $clinicSetting,
            'invoiceSetting' => $invoiceSetting,
        ])->setPaper('a4');

        $relativePath = TenantPatientStoragePaths::invoicePdfRelativePath($invoice);
        Storage::disk(config('filesystems.default'))->put($relativePath, $pdf->output());
        $invoice->update(['pdf_path' => $relativePath]);

        return $relativePath;
    }

    private function invalidateStoredInvoicePdf(Invoice $invoice): void
    {
        $disk = config('filesystems.default');
        $path = $invoice->pdf_path;

        if (is_string($path) && $path !== '') {
            Storage::disk($disk)->delete($path);
        }

        $invoice->update(['pdf_path' => null]);
    }

    private function clinicStaff(): StaffMember|JsonResponse
    {
        $staff = auth('clinic_session')->user();
        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return $staff;
    }

    private function isClinicAdmin(StaffMember $staff): bool
    {
        return in_array($staff->clinic_access_level, ['super_admin', 'admin'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceList(Invoice $inv): array
    {
        return [
            'id' => $inv->id,
            'patient_id' => $inv->patient_id,
            'invoice_number' => $inv->invoice_number,
            'date' => $inv->date instanceof CarbonInterface ? $inv->date->format('Y-m-d') : (string) $inv->date,
            'due_date' => $inv->due_date instanceof CarbonInterface ? $inv->due_date->format('Y-m-d') : (string) $inv->due_date,
            'amount' => $inv->amount,
            'vat_rate' => $inv->vat_rate,
            'status' => $inv->status,
            'patient' => $inv->relationLoaded('patient') && $inv->patient !== null ? [
                'id' => $inv->patient->id,
                'name' => $inv->patient->name,
                'surname' => $inv->patient->surname,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceDetail(Invoice $inv): array
    {
        $base = $this->serializeInvoiceList($inv);
        $base['treatment_entries'] = $inv->relationLoaded('treatmentEntries')
            ? $inv->treatmentEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'price' => $entry->price,
                    'tooth_number' => $entry->tooth_number,
                    'treatment_type' => $entry->relationLoaded('treatmentType') && $entry->treatmentType !== null
                        ? ['id' => $entry->treatmentType->id, 'name' => $entry->treatmentType->name]
                        : null,
                    'dentist' => $entry->relationLoaded('dentist') && $entry->dentist !== null
                        ? ['id' => $entry->dentist->id, 'name' => $entry->dentist->name]
                        : null,
                ];
            })->values()->all()
            : [];

        return $base;
    }
}
