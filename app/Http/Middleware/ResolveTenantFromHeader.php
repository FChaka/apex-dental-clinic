<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/platform/*') || $request->is('sanctum/csrf-cookie') || $request->is('api/health')) {
            return $next($request);
        }

        $slug = $request->header('X-Tenant-Slug');

        // For the staff avatar stream endpoint we allow a query param fallback.
        if (($slug === null || $slug === '') && $request->isMethod('GET') && $request->is('api/staff/*/avatar')) {
            $slug = $request->query('tenant');
        }

        if ($slug === null || $slug === '') {
            return response()->json(['message' => 'Missing X-Tenant-Slug header.'], 400);
        }

        /** @var class-string<Model> $domainModel */
        $domainModel = config('tenancy.domain_model');

        $domain = $domainModel::query()->where('domain', $slug)->first();

        if ($domain === null) {
            return response()->json(['message' => 'Clinic not found.'], 404);
        }

        tenancy()->initialize($domain->tenant);

        return $next($request);
    }
}
