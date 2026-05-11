<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant\Appointment;
use App\Observers\AppointmentObserver;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\DatabaseConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class);
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);

        DatabaseConfig::generateDatabaseNamesUsing(function ($tenant): string {
            $slug = str_replace('-', '_', (string) $tenant->getTenantKey());

            return 'apex_clinic_'.$slug;
        });
    }
}
