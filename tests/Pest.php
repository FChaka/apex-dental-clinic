<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TenancyFeatureTestCase;

/** @var array<int, Clinic> */
$__tenantsToDrop = [];

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TenancyFeatureTestCase::class)->in(
    'Feature/Auth/ClinicAuthTest.php',
    'Feature/Auth/PlatformAuthTest.php',
);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Tenancy helpers (IMPLEMENTATION_STRATEGY §8)
|--------------------------------------------------------------------------
*/

function createTestTenant(?string $slug = null): Clinic
{
    global $__tenantsToDrop;

    $slug ??= 't'.str_replace('.', '', uniqid('', true));
    $dbName = 'apex_clinic_'.str_replace('-', '_', $slug);

    // Pre-cleanup: remove leftover DB file from a crashed prior run
    $dbPath = database_path($dbName);
    if (is_file($dbPath)) {
        @unlink($dbPath);
    }

    $clinic = Clinic::query()->create([
        'name' => 'Test Clinic',
        'slug' => $slug,
        'contact_email' => 'test@example.com',
        'status' => 'active',
        'db_name' => $dbName,
    ]);

    $clinic->domains()->create([
        'domain' => $slug,
    ]);

    $__tenantsToDrop[] = $clinic;

    return $clinic->fresh() ?? $clinic;
}

function tenantHttpHost(Clinic $clinic): string
{
    return $clinic->slug.'.apex.test';
}

/**
 * Host for the API (single domain for all tenants), e.g. api.apex.test when APP_URL is http://apex.test.
 */
function apiHttpHost(): string
{
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);

    return is_string($host) && $host !== '' ? 'api.'.$host : 'api.apex.test';
}

/**
 * Clinic SPA origin URL (Referer) — {slug}.apex.test.
 */
function tenantUrl(Clinic $clinic, string $path): string
{
    return 'http://'.tenantHttpHost($clinic).'/'.ltrim($path, '/');
}

/**
 * Absolute URL to the API for clinic routes (requires X-Tenant-Slug).
 */
function clinicApiUrl(Clinic $clinic, string $path): string
{
    return 'http://'.apiHttpHost().'/'.ltrim($path, '/');
}

/**
 * Referer + tenant slug so Sanctum stateful middleware applies and ResolveTenantFromHeader can initialize tenancy.
 *
 * @return array<string, string>
 */
function clinicStatefulHeaders(Clinic $clinic): array
{
    return [
        'Referer' => tenantUrl($clinic, '/'),
        'X-Tenant-Slug' => $clinic->slug,
    ];
}

/**
 * @return array<string, string>
 */
function platformStatefulHeaders(): array
{
    return ['Referer' => rtrim((string) config('app.url'), '/').'/'];
}

/**
 * @return array<string, string>
 */
function sessionCookiesFromResponse(TestResponse $response): array
{
    $cookie = $response->getCookie(config('session.cookie'));

    if ($cookie === null) {
        return [];
    }

    return [$cookie->getName() => $cookie->getValue()];
}

function dropTenantDatabaseIfExists(Clinic $clinic): void
{
    tenancy()->end();

    $name = str_replace('`', '', $clinic->database()->getName());
    $driver = (string) config('database.connections.'.config('tenancy.database.template_tenant_connection').'.driver');

    if ($driver === 'sqlite') {
        $path = database_path($name);

        if (is_file($path)) {
            @unlink($path);
        }

        return;
    }

    DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `'.$name.'`');
}

afterEach(function () {
    global $__tenantsToDrop;

    foreach (array_reverse($__tenantsToDrop) as $clinic) {
        try {
            dropTenantDatabaseIfExists($clinic);
        } catch (Throwable) {
            // Best-effort cleanup; tests should still fail on assertions, not cleanup.
        }
    }

    $__tenantsToDrop = [];
});
