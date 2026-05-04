<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientXray;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use App\Support\TenantPatientStoragePaths;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

final class PatientXrayController extends Controller
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

        $items = PatientXray::query()
            ->where('patient_id', $patient->id)
            ->with('uploader:id,name')
            ->orderByDesc('taken_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PatientXray $x) => $this->serializeXray($x, $patient))
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
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,bmp,tif,tiff', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'taken_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $disk = config('filesystems.default');
        $xrayDir = TenantPatientStoragePaths::xrayDirectory($patient);
        $thumbsDir = TenantPatientStoragePaths::xrayThumbsDirectory($patient);
        $manager = ImageManager::gd();

        $created = [];
        $takenAt = isset($validated['taken_at']) ? Carbon::parse($validated['taken_at'])->startOfDay() : null;

        foreach ($validated['files'] as $file) {
            $baseId = (string) Str::uuid();
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $extension = Str::lower($extension);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }
            if (! in_array($extension, ['jpg', 'png', 'webp', 'bmp', 'tif', 'tiff'], true)) {
                $extension = 'jpg';
            }
            $storedName = $baseId.'.'.$extension;
            $storedPath = $file->storeAs(
                $xrayDir,
                $storedName,
                ['disk' => $disk]
            );

            $thumbRelative = $thumbsDir.'/'.$baseId.'.jpg';
            $this->writeThumbnailJpegToDisk(
                $manager,
                $disk,
                $storedPath,
                $thumbRelative
            );

            $mime = (string) $file->getMimeType();
            if ($mime === '' || $mime === 'application/octet-stream') {
                $mime = 'image/jpeg';
            }

            $record = PatientXray::query()->create([
                'patient_id' => $patient->id,
                'title' => $validated['title'] ?? null,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'thumbnail_path' => $thumbRelative,
                'mime_type' => $mime,
                'file_size' => (int) $file->getSize(),
                'notes' => $validated['notes'] ?? null,
                'taken_at' => $takenAt,
                'uploaded_by' => $staff->id,
            ]);

            $record->load('uploader:id,name');
            $created[] = $this->serializeXray($record, $patient);
        }

        return JsonApiResponse::success($created, 'X-ray uploaded successfully.', 201);
    }

    public function show(Patient $patient, PatientXray $xray): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $xray->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }

        $xray->load('uploader:id,name');

        return JsonApiResponse::success($this->serializeXray($xray, $patient), 'OK');
    }

    public function update(Request $request, Patient $patient, PatientXray $xray): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $xray->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'taken_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        if (array_key_exists('taken_at', $validated) && $validated['taken_at'] !== null) {
            $xray->taken_at = Carbon::parse($validated['taken_at'])->startOfDay();
        } elseif (array_key_exists('taken_at', $validated)) {
            $xray->taken_at = null;
        }

        if (array_key_exists('title', $validated)) {
            $xray->title = $validated['title'];
        }

        if (array_key_exists('notes', $validated)) {
            $xray->notes = $validated['notes'];
        }

        $xray->save();

        return JsonApiResponse::success(
            $this->serializeXray($xray->fresh()->load('uploader:id,name'), $patient),
            'X-ray updated successfully.',
        );
    }

    public function destroy(Patient $patient, PatientXray $xray): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $xray->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }

        $disk = config('filesystems.default');

        DB::transaction(function () use ($xray, $disk) {
            if ($xray->file_path !== '' && Storage::disk($disk)->exists($xray->file_path)) {
                Storage::disk($disk)->delete($xray->file_path);
            }
            $thumb = $xray->thumbnail_path;
            if (is_string($thumb) && $thumb !== '' && Storage::disk($disk)->exists($thumb)) {
                Storage::disk($disk)->delete($thumb);
            }
            $xray->delete();
        });

        return JsonApiResponse::success(null, 'X-ray deleted successfully.');
    }

    public function image(Patient $patient, PatientXray $xray): Response|JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $xray->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }

        return $this->streamXrayFile($xray->file_path, $xray->mime_type, $xray->file_name, inline: true);
    }

    public function thumbnail(Patient $patient, PatientXray $xray): Response|JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $xray->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }

        $path = $xray->thumbnail_path;
        if (! is_string($path) || $path === '') {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }

        return $this->streamXrayFile($path, 'image/jpeg', pathinfo($xray->file_name, PATHINFO_FILENAME).'.jpg', inline: true);
    }

    private function writeThumbnailJpegToDisk(
        ImageManager $manager,
        string $disk,
        string $storedPath,
        string $thumbRelative
    ): void {
        $contents = Storage::disk($disk)->get($storedPath);
        $image = $manager->read($contents);
        $image->scaleDown(400, 400);
        Storage::disk($disk)->put($thumbRelative, $image->toJpeg(80)->toString());
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeXray(PatientXray $x, Patient $patient): array
    {
        $uploader = $x->uploader;
        $uploadedBy = null;
        if ($uploader !== null) {
            $uploadedBy = [
                'id' => $uploader->id,
                'name' => $uploader->name,
            ];
        }

        $imageUrl = url()->route('api.patients.xrays.image', [
            'patient' => $patient->id,
            'xray' => $x->id,
        ], true);
        $thumbUrl = url()->route('api.patients.xrays.thumbnail', [
            'patient' => $patient->id,
            'xray' => $x->id,
        ], true);

        return [
            'id' => $x->id,
            'title' => $x->title,
            'file_name' => $x->file_name,
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbUrl,
            'mime_type' => $x->mime_type,
            'file_size' => $x->file_size,
            'notes' => $x->notes,
            'taken_at' => $x->taken_at?->toDateString(),
            'uploaded_by' => $uploadedBy,
            'created_at' => $x->created_at?->toIso8601String(),
            'updated_at' => $x->updated_at?->toIso8601String(),
        ];
    }

    private function streamXrayFile(
        string $relativePath,
        string $mime,
        string $filename,
        bool $inline
    ): Response|JsonResponse {
        $disk = config('filesystems.default');
        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            return response()->json(['message' => 'X-ray not found.'], 404);
        }
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $headers = [
            'Content-Type' => $mime,
        ];
        if ($inline) {
            $headers['Content-Disposition'] = 'inline; filename="'.$this->dispositionFileName($filename).'"';
        } else {
            $headers['Content-Disposition'] = 'attachment; filename="'.$this->dispositionFileName($filename).'"';
        }

        $binary = Storage::disk($disk)->get($relativePath);

        return response($binary, 200, $headers);
    }

    private function dispositionFileName(string $name): string
    {
        $safe = str_replace(['"', "\r", "\n"], '', $name);

        return $safe !== '' ? $safe : 'xray';
    }
}
