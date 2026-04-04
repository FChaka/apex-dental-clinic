<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Stored in the active tenant database (default connection after tenancy is initialized).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    //
}
