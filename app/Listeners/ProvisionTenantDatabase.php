<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;

class ProvisionTenantDatabase
{
    public function handle(TenantCreated $event): void
    {
        if (app()->environment('testing') && in_array(config('tenancy.database.template_tenant_connection'), ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            return;
        }

        $tenant = $event->tenant;

        if (config('tenancy.database.template_tenant_connection') === 'sqlite') {
            config()->set('database.connections.sqlite.database', database_path($tenant->database()->getName()));
        }

        $databaseManager = app(DatabaseManager::class);

        // In testing, remove leftover DB from a prior crashed run so CreateDatabase won't throw.
        if (app()->environment('testing')) {
            $manager = $tenant->database()->manager();
            if ($manager->databaseExists($tenant->database()->getName())) {
                $manager->deleteDatabase($tenant);
            }
        }

        (new CreateDatabase($tenant))->handle($databaseManager);

        tenancy()->initialize($tenant);

        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        tenancy()->end();
    }
}
