<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Clinic;
use App\Models\Tenant\StaffMember;
use App\Support\ClinicSanctumTokenBinding;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * For clinic staff, re-validates or establishes tenancy from the Sanctum token name ({@see ClinicSanctumTokenBinding}).
 * Session (SPA) auth uses a {@see TransientToken}; tenant context then comes from the request host ({@see InitializeTenancyBySubdomain}) only.
 */
final class InitializeTenancyFromToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('clinic');

        if (! $user instanceof StaffMember) {
            return $next($request);
        }

        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof TransientToken || ! $accessToken instanceof PersonalAccessToken) {
            return $next($request);
        }

        $clinicId = ClinicSanctumTokenBinding::parseClinicId((string) $accessToken->name);

        if ($clinicId === null) {
            abort(403, 'Clinic staff token is missing a valid clinic binding.');
        }

        /** @var Clinic|null $clinic */
        $clinic = Clinic::query()->whereKey($clinicId)->first();

        if ($clinic === null) {
            abort(403, 'Clinic staff token references an unknown clinic.');
        }

        if (tenant()) {
            if (tenant()->getTenantKey() !== $clinic->getTenantKey()) {
                abort(403, 'Token does not match the requested clinic.');
            }
        } else {
            tenancy()->initialize($clinic);
        }

        return $next($request);
    }
}
