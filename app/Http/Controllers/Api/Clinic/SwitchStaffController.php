<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StaffMember;
use App\Services\SwitchStaffOtpService;
use App\Support\JsonApiResponse;
use App\Support\StaffPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class SwitchStaffController extends Controller
{
    public function __construct(
        private readonly SwitchStaffOtpService $otpService,
    ) {}

    public function switchStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_staff_id' => ['required', 'integer'],
        ]);

        $target = StaffMember::query()->find($validated['target_staff_id']);

        if ($target === null) {
            return response()->json(['message' => 'Staff member not found.'], 404);
        }

        if ($target->phone === null || $target->phone === '') {
            return response()->json([
                'message' => 'This staff member has no registered phone number.',
            ], 422);
        }

        $tenantSlug = (string) tenancy()->tenant->slug;

        try {
            $this->otpService->generate($target, $tenantSlug);
        } catch (\Throwable $e) {
            Log::error('Switch-staff OTP send failed', [
                'staff_id' => $target->id,
                'tenant_slug' => $tenantSlug,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to send verification code. Please try again.',
            ], 500);
        }

        return JsonApiResponse::success(
            null,
            'Verification code sent to the staff member\'s phone.'
        );
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_staff_id' => ['required', 'integer'],
            'otp' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ]);

        $target = StaffMember::query()->find($validated['target_staff_id']);

        if ($target === null) {
            return response()->json(['message' => 'Staff member not found.'], 404);
        }

        $tenantSlug = (string) tenancy()->tenant->slug;
        $valid = $this->otpService->verify($target, $tenantSlug, $validated['otp']);

        if (! $valid) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        Auth::guard('clinic_session')->login($target);
        $request->session()->regenerate();

        return JsonApiResponse::success([
            'staff' => self::serializeStaff($target),
            'permissions' => StaffPermissions::forStaff($target),
        ], 'Staff switched successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeStaff(StaffMember $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'username' => $staff->username,
            'role' => $staff->role,
            'clinic_access_level' => $staff->clinic_access_level,
            'status' => $staff->status,
            'sign_in_method' => $staff->sign_in_method,
        ];
    }
}
