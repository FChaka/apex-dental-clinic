<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\DatabaseConfig;
use Twilio\Rest\Client as TwilioClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TwilioClient::class, function (): TwilioClient {
            return new TwilioClient(
                (string) config('services.twilio.sid'),
                (string) config('services.twilio.token')
            );
        });
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
