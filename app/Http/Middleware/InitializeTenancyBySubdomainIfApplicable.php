<?php

// DEPRECATED — replaced by ResolveTenantFromHeader. Safe to delete after confirming all routes work.

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;

final class InitializeTenancyBySubdomainIfApplicable
{
    public function handle(Request $request, Closure $next): mixed
    {
        $centralDomains = config('tenancy.central_domains', []);

        if (in_array($request->getHost(), $centralDomains, true)) {
            return $next($request);
        }

        return app(InitializeTenancyBySubdomain::class)->handle($request, $next);
    }
}
