<?php

declare(strict_types=1);

use App\Models\Tenant\StaffMember;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('{tenantSlug}.staff.{staffId}', function ($user, string $tenantSlug, string $staffId) {
    if (! $user instanceof StaffMember) {
        return false;
    }

    if (! tenancy()->initialized || (string) tenant()->getTenantKey() !== $tenantSlug) {
        return false;
    }

    return (int) $user->id === (int) $staffId;
}, ['guards' => ['clinic_session']]);
