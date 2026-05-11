<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ProvisionTenantDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;

/**
 * Registers stancl tenancy lifecycle listeners.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Events\TenantCreated::class, ProvisionTenantDatabase::class);
        Event::listen(Events\TenancyInitialized::class, Listeners\BootstrapTenancy::class);
        Event::listen(Events\TenancyEnded::class, Listeners\RevertToCentralContext::class);
    }
}
