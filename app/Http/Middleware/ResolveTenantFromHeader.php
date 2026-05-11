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

        $slugFromHeader = $this->normalizedSlug($request->header('X-Tenant-Slug'));

        // For the staff avatar stream endpoint we allow a query param fallback.
        $slugFromAvatar = null;
        if ($request->isMethod('GET') && $request->is('api/staff/*/avatar')) {
            $slugFromAvatar = $this->normalizedSlug($request->query('tenant'));
        }

        $slugFromBroadcastingHost = $this->validatedBroadcastingSlugFromHost($request);

        if ($request->is('broadcasting/*')) {
            if ($slugFromHeader !== null
                && $slugFromBroadcastingHost !== null
                && $slugFromHeader !== $slugFromBroadcastingHost) {
                return response()->json(['message' => 'X-Tenant-Slug does not match tenant host.'], 400);
            }
        }

        $slug = $slugFromHeader ?? $slugFromAvatar;

        if (($slug === null || $slug === '') && $request->is('broadcasting/*')) {
            $slug = $slugFromBroadcastingHost;
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

    private function normalizedSlug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function validatedBroadcastingSlugFromHost(Request $request): ?string
    {
        if (! $request->is('broadcasting/*')) {
            return null;
        }

        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        if (in_array($host, $centralDomains, true)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $candidate = strtolower(explode('.', $host, 2)[0] ?? '');

        if ($candidate === '') {
            return null;
        }

        /** @var class-string<Model> $domainModel */
        $domainModel = config('tenancy.domain_model');
        $exists = $domainModel::query()->where('domain', $candidate)->exists();

        return $exists ? $candidate : null;
    }
}
