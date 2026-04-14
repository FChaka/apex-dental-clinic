<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClinicSetting;
use App\Models\Tenant\DateTimeSetting;
use App\Models\Tenant\InvoiceSetting;
use App\Models\Tenant\StaffMember;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class ClinicSettingsController extends Controller
{
    public function showGeneral(): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $setting = ClinicSetting::query()->firstOrCreate(
            ['id' => 1],
            ['clinic_name' => '']
        );

        return JsonApiResponse::success($this->serializeClinicSetting($setting), 'OK');
    }

    public function updateGeneral(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'clinic_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_nr' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'facebook_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tiktok_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand_color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'default_vat' => ['sometimes', 'nullable', 'numeric'],
            'logo' => ['sometimes', 'file', 'max:10240'],
        ]);

        /** @var UploadedFile|null $logo */
        $logo = $validated['logo'] ?? null;
        unset($validated['logo']);

        $setting = ClinicSetting::query()->firstOrNew(['id' => 1]);

        if ($logo instanceof UploadedFile) {
            $disk = config('filesystems.default');
            $oldPath = $setting->logo_path;
            if (is_string($oldPath) && $oldPath !== '') {
                Storage::disk($disk)->delete($oldPath);
            }

            $tenantSlug = (string) tenancy()->tenant->slug;
            $ext = $logo->getClientOriginalExtension() ?: 'bin';
            $path = "tenants/{$tenantSlug}/settings/logo.{$ext}";

            Storage::disk($disk)->putFileAs(
                "tenants/{$tenantSlug}/settings",
                $logo,
                "logo.{$ext}"
            );

            $validated['logo_path'] = $path;
        }

        $saved = ClinicSetting::query()->updateOrCreate(['id' => 1], $validated);

        return JsonApiResponse::success($this->serializeClinicSetting($saved), 'OK');
    }

    public function showInvoice(): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $setting = InvoiceSetting::query()->firstOrCreate(['id' => 1], []);

        return JsonApiResponse::success($this->serializeInvoiceSetting($setting), 'OK');
    }

    public function updateInvoice(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'iban' => ['sometimes', 'nullable', 'string', 'max:50'],
            'swift' => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'other_details' => ['sometimes', 'nullable', 'string'],
        ]);

        $saved = InvoiceSetting::query()->updateOrCreate(['id' => 1], $validated);

        return JsonApiResponse::success($this->serializeInvoiceSetting($saved), 'OK');
    }

    public function showDateTime(): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $setting = DateTimeSetting::query()->firstOrCreate(['id' => 1], []);

        return JsonApiResponse::success($this->serializeDateTimeSetting($setting), 'OK');
    }

    public function updateDateTime(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'time_zone_mode' => ['sometimes', Rule::in(['automatic', 'manual'])],
            'manual_time_zone' => ['nullable', 'string', 'max:100'],
            'date_format' => ['sometimes', Rule::in(['dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy-mm-dd'])],
        ]);

        $saved = DateTimeSetting::query()->updateOrCreate(['id' => 1], $validated);

        return JsonApiResponse::success($this->serializeDateTimeSetting($saved), 'OK');
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
    private function serializeClinicSetting(ClinicSetting $s): array
    {
        return [
            'clinic_name' => $s->clinic_name,
            'phone' => $s->phone,
            'email' => $s->email,
            'address' => $s->address,
            'website' => $s->website,
            'business_nr' => $s->business_nr,
            'city' => $s->city,
            'zip_code' => $s->zip_code,
            'facebook_url' => $s->facebook_url,
            'instagram_url' => $s->instagram_url,
            'tiktok_url' => $s->tiktok_url,
            'logo_path' => $s->logo_path,
            'brand_color' => $s->brand_color,
            'currency' => $s->currency,
            'default_vat' => $s->default_vat,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceSetting(InvoiceSetting $s): array
    {
        return [
            'bank_name' => $s->bank_name,
            'iban' => $s->iban,
            'swift' => $s->swift,
            'account_holder' => $s->account_holder,
            'other_details' => $s->other_details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDateTimeSetting(DateTimeSetting $s): array
    {
        return [
            'time_zone_mode' => $s->time_zone_mode,
            'manual_time_zone' => $s->manual_time_zone,
            'date_format' => $s->date_format,
        ];
    }
}
