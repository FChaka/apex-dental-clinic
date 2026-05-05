<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Central\Clinic;
use App\Models\Tenant\StaffMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ClinicProvisioningService
{
    /**
     * Create central clinic row and domain inside a transaction; the synchronous
     * {@see \App\Listeners\ProvisionTenantDatabase} listener provisions the tenant database.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createCentralClinic(array $validated): Clinic
    {
        $slug = (string) $validated['slug'];
        $dbName = 'apex_clinic_'.str_replace('-', '_', $slug);

        return DB::connection('central')->transaction(function () use ($validated, $dbName, $slug) {
            /** @var Clinic $clinic */
            $clinic = Clinic::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'contact_email' => $validated['contact_email'],
                'plan' => $validated['plan'],
                'seats' => $validated['seats'] ?? 5,
                'region' => $validated['region'] ?? null,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'mrr' => 0,
                'db_name' => $dbName,
            ]);

            $clinic->domains()->create([
                'domain' => $slug,
            ]);

            return $clinic->fresh();
        });
    }

    /**
     * Seed tenant defaults and create the provisional owner inside the clinic database.
     *
     * @param  array<string, mixed>  $validated
     * @return array{owner: StaffMember, temporary_pin: string, pin_expires_at: Carbon}
     */
    public function bootstrapNewClinicTenant(Clinic $clinic, array $validated): array
    {
        $temporaryPin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pinExpiresAt = now()->addHours(24);

        /** @var StaffMember $owner */
        $owner = $clinic->run(function () use ($validated, $temporaryPin, $pinExpiresAt) {
            TenantDefaultDataService::seed($validated['name']);

            return StaffMember::query()->create([
                'name' => $validated['owner_name'],
                'email' => $validated['contact_email'],
                'username' => $validated['owner_username'],
                'role' => 'Dentist',
                'clinic_access_level' => 'super_admin',
                'status' => 'Active',
                'sign_in_method' => 'pin',
                'pin_length' => 6,
                'login_pin' => $temporaryPin,
                'temp_pin_expires_at' => $pinExpiresAt,
                'must_change_credentials' => true,
            ]);
        });

        return [
            'owner' => $owner,
            'temporary_pin' => $temporaryPin,
            'pin_expires_at' => $pinExpiresAt,
        ];
    }
}
