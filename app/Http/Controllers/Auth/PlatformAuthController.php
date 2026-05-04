<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PlatformLoginRequest;
use App\Models\Central\PlatformAdmin;
use App\Services\AuditService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Platform admin JSON auth.
 */
final class PlatformAuthController extends Controller
{
    public function login(PlatformLoginRequest $request): JsonResponse
    {
        $admin = PlatformAdmin::query()->where('email', $request->validated('email'))->first();

        if ($admin === null || ! Hash::check($request->validated('password'), (string) $admin->getRawOriginal('password'))) {
            return JsonApiResponse::unauthorized('Invalid credentials.');
        }

        Auth::guard('platform_session')->login($admin);
        $request->session()->regenerate();

        AuditService::log('platform_admin.login', null, null, [
            'email' => $admin->email,
        ]);

        return JsonApiResponse::success([
            'admin' => self::serializeAdmin($admin),
        ], 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        AuditService::log('platform_admin.logout');

        Auth::guard('platform_session')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return JsonApiResponse::success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $admin = Auth::guard('platform_session')->user();

        if (! $admin instanceof PlatformAdmin) {
            return JsonApiResponse::unauthorized();
        }

        return JsonApiResponse::success([
            'admin' => self::serializeAdmin($admin),
        ], 'OK');
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeAdmin(PlatformAdmin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ];
    }
}
