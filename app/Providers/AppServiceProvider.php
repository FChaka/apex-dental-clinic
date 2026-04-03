<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\DatabaseConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing(function ($tenant): string {
            $slug = str_replace('-', '_', (string) $tenant->getTenantKey());

            return 'apex_clinic_'.$slug;
        });
    }
}
