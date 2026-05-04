<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientDocument;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use App\Support\TenantPatientStoragePaths;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PatientDocumentController extends Controller
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

        $items = PatientDocument::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PatientDocument $d) => $this->serializeDocument($d))
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
            'file' => ['required', 'file', 'max:51200'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        $file = $validated['file'];
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $storedName = Str::uuid()->toString().'.'.$extension;

        $disk = config('filesystems.default');
        $storedPath = $file->storeAs(
            TenantPatientStoragePaths::documentsDirectory($patient),
            $storedName,
            ['disk' => $disk]
        );

        $document = PatientDocument::query()->create([
            'patient_id' => $patient->id,
            'name' => $validated['name'],
            'file_name' => $file->getClientOriginalName(),
            'type' => $validated['type'],
            'file_path' => $storedPath,
        ]);

        return JsonApiResponse::success(
            $this->serializeDocument($document),
            'Document uploaded successfully.',
            201
        );
    }

    public function download(Patient $patient, PatientDocument $document): Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $document->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $disk = config('filesystems.default');
        $path = (string) $document->file_path;

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $mime = (string) $document->type;
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        return Storage::disk($disk)->download($path, $document->file_name, [
            'Content-Type' => $mime,
        ]);
    }

    public function destroy(Patient $patient, PatientDocument $document): Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $document->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $disk = config('filesystems.default');

        DB::transaction(function () use ($document, $disk) {
            if ($document->file_path !== '' && Storage::disk($disk)->exists($document->file_path)) {
                Storage::disk($disk)->delete($document->file_path);
            }
            $document->delete();
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(PatientDocument $d): array
    {
        return [
            'id' => $d->id,
            'patient_id' => $d->patient_id,
            'name' => $d->name,
            'file_name' => $d->file_name,
            'type' => $d->type,
            'file_path' => $d->file_path,
            'created_at' => $d->created_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }
}
