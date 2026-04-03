<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/**
 * Registers stancl tenancy lifecycle listeners.
 *
 * Tenant provisioning (CreateDatabase, MigrateDatabase, etc.) is deferred until Slice 1 Step 2+.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        InitializeTenancyByRequestData::$header = 'X-Clinic-Slug';
        InitializeTenancyByRequestData::$queryParameter = 'clinic_slug';

        Event::listen(Events\TenancyInitialized::class, Listeners\BootstrapTenancy::class);
        Event::listen(Events\TenancyEnded::class, Listeners\RevertToCentralContext::class);
    }
}
