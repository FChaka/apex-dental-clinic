<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StaffMember;
use App\Support\JsonApiResponse;
use App\Support\StaffPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class SwitchStaffController extends Controller
{
    public function switchStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_staff_id' => ['required', 'integer'],
            'credential' => ['required', 'string'],
        ]);

        $target = StaffMember::query()->find($validated['target_staff_id']);

        if ($target === null) {
            return response()->json(['message' => 'Staff member not found.'], 404);
        }

        $credential = $validated['credential'];

        $valid = match ($target->sign_in_method) {
            'pin' => $credential !== '' && Hash::check($credential, (string) $target->getRawOriginal('login_pin')),
            'password' => $credential !== '' && Hash::check($credential, (string) $target->getRawOriginal('login_password')),
            default => false,
        };

        if (! $valid) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
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
