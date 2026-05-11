<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StaffDocument;
use App\Models\Tenant\StaffMember;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class StaffDocumentController extends Controller
{
    public function index(StaffMember $staff): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $this->authorizeForUser($auth, 'view', $staff);

        $items = StaffDocument::query()
            ->where('staff_id', $staff->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (StaffDocument $d) => $this->serializeDocument($d))
            ->values()
            ->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request, StaffMember $staff): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if (! $this->isClinicAdmin($auth)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['license', 'diploma', 'certification', 'other'])],
        ]);

        $file = $validated['file'];
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $storedName = Str::uuid()->toString().'.'.$extension;

        $tenantSlug = (string) tenancy()->tenant->slug;
        $disk = config('filesystems.default');
        $dir = "tenants/{$tenantSlug}/staff/{$staff->id}/documents";

        $storedPath = $file->storeAs($dir, $storedName, ['disk' => $disk]);

        $doc = StaffDocument::query()->create([
            'staff_id' => $staff->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'uploaded_at' => now(),
        ]);

        return JsonApiResponse::success($this->serializeDocument($doc), 'OK', 201);
    }

    public function destroy(StaffMember $staff, StaffDocument $document): Response
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if (! $this->isClinicAdmin($auth)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ((int) $document->staff_id !== (int) $staff->id) {
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
    private function serializeDocument(StaffDocument $d): array
    {
        return [
            'id' => $d->id,
            'staff_id' => $d->staff_id,
            'name' => $d->name,
            'type' => $d->type,
            'file_name' => $d->file_name,
            'file_path' => $d->file_path,
            'uploaded_at' => $d->uploaded_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }
}
