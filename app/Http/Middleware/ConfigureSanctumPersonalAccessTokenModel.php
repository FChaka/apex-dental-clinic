<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\PersonalAccessToken as CentralPersonalAccessToken;
use App\Models\Tenant\PersonalAccessToken as TenantPersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum resolves bearer tokens via a single static model; swap it per-route stack (platform central DB vs tenant DB).
 */
final class ConfigureSanctumPersonalAccessTokenModel
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $context = 'clinic'): Response
    {
        $model = match ($context) {
            'platform' => CentralPersonalAccessToken::class,
            default => TenantPersonalAccessToken::class,
        };

        $previous = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel($model);

        try {
            return $next($request);
        } finally {
            Sanctum::usePersonalAccessTokenModel($previous);
        }
    }
}
