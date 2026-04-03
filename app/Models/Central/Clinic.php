<?php

declare(strict_types=1);

namespace App\Models\Central;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Central registry row for a clinic; also the stancl tenant record.
 *
 * @property int $id
 * @property string $slug
 * @property array|null $data
 */
class Clinic extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    protected $table = 'clinics';

    /**
     * Tenant resolution uses the public clinic slug (e.g. login payload `clinic_slug`).
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
}
