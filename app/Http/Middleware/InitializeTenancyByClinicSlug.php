<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/**
 * Resolves tenancy from `clinic_slug` in the JSON/form body (e.g. login), then header/query fallbacks.
 */
class InitializeTenancyByClinicSlug extends InitializeTenancyByRequestData
{
    protected function getPayload(Request $request): ?string
    {
        $slug = $request->input('clinic_slug');

        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        return parent::getPayload($request);
    }
}
