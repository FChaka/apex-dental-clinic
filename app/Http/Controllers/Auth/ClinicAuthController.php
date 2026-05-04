<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClinicLoginRequest;
use App\Models\Tenant\StaffMember;
use App\Services\Auth\ClinicAuthService;
use App\Support\JsonApiResponse;
use App\Support\StaffAvatarUrl;
use App\Support\StaffPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Clinic JSON auth. Validation failures return 422 with Laravel's default `errors` shape.
 */
final class ClinicAuthController extends Controller
{
    public function login(ClinicLoginRequest $request, ClinicAuthService $auth): JsonResponse
    {
        $staff = $auth->findStaffByUsername($request->validated('username'));

        if ($staff === null) {
            return JsonApiResponse::unauthorized('Invalid credentials.');
        }

        if ($staff->sign_in_method === 'pin') {
            if (! $request->filled('pin')) {
                throw ValidationException::withMessages([
                    'pin' => ['PIN is required for this account.'],
                ]);
            }
        } elseif ($staff->sign_in_method === 'password') {
            if (! $request->filled('password') || $request->input('password') === '') {
                throw ValidationException::withMessages([
                    'password' => ['Password is required for this account.'],
                ]);
            }
        }

        if (! $auth->verifyCredentials($staff, $request->input('pin'), $request->input('password'))) {
            return JsonApiResponse::unauthorized('Invalid credentials.');
        }

        Auth::guard('clinic_session')->login($staff);
        $request->session()->regenerate();

        return JsonApiResponse::success([
            'staff' => self::serializeStaff($staff),
            'permissions' => StaffPermissions::forStaff($staff),
        ], 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('clinic_session')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return JsonApiResponse::success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $staff = Auth::guard('clinic_session')->user();

        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return JsonApiResponse::success([
            'staff' => self::serializeStaff($staff),
            'permissions' => StaffPermissions::forStaff($staff),
        ], 'OK');
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
            'avatar_path' => $staff->avatar_path,
            'avatar_url' => StaffAvatarUrl::forStaffMember($staff),
        ];
    }
}
