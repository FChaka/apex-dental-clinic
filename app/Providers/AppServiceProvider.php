<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant\Appointment;
use App\Models\Tenant\StaffMember;
use App\Observers\AppointmentObserver;
use App\Policies\StaffMemberPolicy;
use App\Services\DataScopeService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\DatabaseConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataScopeService::class);
        $this->app->singleton(NotificationService::class);
    }

    public function boot(): void
    {
        Gate::policy(StaffMember::class, StaffMemberPolicy::class);

        Appointment::observe(AppointmentObserver::class);

        DatabaseConfig::generateDatabaseNamesUsing(function ($tenant): string {
            $slug = str_replace('-', '_', (string) $tenant->getTenantKey());

            return 'apex_clinic_'.$slug;
        });
    }
}
