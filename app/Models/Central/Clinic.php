<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\ClinicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;

/**
 * Central registry row for a clinic; also the stancl tenant record.
 *
 * Tenant identification on the web uses {@see InitializeTenancyBySubdomain}:
 * each row in `domains` should use the same string as `slug` (e.g. domain `smile` for `smile.apex.com`).
 *
 * Connection is resolved via Stancl {@see CentralConnection}; do not set
 * {@see $connection} here.
 *
 * @property int $id
 * @property string $slug
 * @property array|null $data
 */
class Clinic extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<ClinicFactory> */
    use HasDatabase, HasDomains, HasFactory, SoftDeletes;

    protected $table = 'clinics';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Stancl tenant key column on `clinics` (subdomain label is stored in `domains.domain`, usually equal to this slug).
     */
    public function getTenantKeyName(): string
    {
        return 'slug';
    }

    /**
     * Real DB columns (see central migration); other attributes route through VirtualColumn `data` when needed.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'region',
            'plan',
            'seats',
            'status',
            'contact_email',
            'mrr',
            'db_name',
            'db_host',
            'db_port',
            'trial_ends_at',
            'created_at',
            'updated_at',
            'deleted_at',
            'data',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'clinic_id');
    }

    /**
     * @return HasMany<ClinicService, $this>
     */
    public function clinicServices(): HasMany
    {
        return $this->hasMany(ClinicService::class, 'clinic_id');
    }

    /**
     * @return HasMany<ClinicUsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(ClinicUsageRecord::class, 'clinic_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'clinic_id');
    }

    protected static function newFactory(): ClinicFactory
    {
        return ClinicFactory::new();
    }
}
