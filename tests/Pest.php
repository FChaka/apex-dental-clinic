<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Tests\TenancyFeatureTestCase;

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
    $slug ??= 't'.str_replace('.', '', uniqid('', true));
    $dbName = 'apex_clinic_'.str_replace('-', '_', $slug);

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

    $databaseManager = app(DatabaseManager::class);

    (new CreateDatabase($clinic))->handle($databaseManager);

    tenancy()->initialize($clinic);

    foreach ([
        database_path('migrations/tenant/2026_04_03_210006_create_staff_members_table.php'),
        database_path('migrations/tenant/2026_04_04_210029_create_personal_access_tokens_table.php'),
    ] as $path) {
        Artisan::call('migrate', [
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    tenancy()->end();

    return $clinic->fresh() ?? $clinic;
}

function tenantHttpHost(Clinic $clinic): string
{
    return $clinic->slug.'.apex.test';
}

/**
 * Absolute URL so {@see Request::getHost()} matches subdomain tenancy (HTTP_HOST alone is not always applied by the test client).
 */
function tenantUrl(Clinic $clinic, string $path): string
{
    return 'http://'.tenantHttpHost($clinic).'/'.ltrim($path, '/');
}

function dropTenantDatabaseIfExists(Clinic $clinic): void
{
    tenancy()->end();

    $name = str_replace('`', '', $clinic->database()->getName());
    DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `'.$name.'`');
}
