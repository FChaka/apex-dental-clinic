<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Clinic;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block API requests when the resolved clinic is suspended or has an expired trial.
 *
 * Must run AFTER {@see ResolveTenantFromHeader} so that tenancy is already initialised.
 */
final class CheckClinicStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip routes that don't involve a tenant (platform admin, health, CSRF).
        if (! app(Tenancy::class)->initialized) {
            return $next($request);
        }

        /** @var Clinic $clinic */
        $clinic = tenant();

        $status = $clinic->getAttribute('status');

        if ($status === 'suspended') {
            return response()->json([
                'data' => null,
                'message' => 'This clinic has been suspended. Please contact support.',
            ], 403);
        }

        if ($status === 'trial') {
            $trialEndsAt = $clinic->getAttribute('trial_ends_at');

            if ($trialEndsAt !== null && $trialEndsAt->isPast()) {
                return response()->json([
                    'data' => null,
                    'message' => 'Your free trial has expired. Please subscribe to continue using the platform.',
                ], 403);
            }
        }

        return $next($request);
    }
}
