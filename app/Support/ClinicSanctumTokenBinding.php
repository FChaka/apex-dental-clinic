<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Central\Clinic;

/**
 * Binds a clinic staff Sanctum token to a central {@see Clinic} row (token `name`, not tenant table columns).
 */
final class ClinicSanctumTokenBinding
{
    public const PREFIX = 'clinic:';

    public static function tokenNameForClinic(Clinic $clinic): string
    {
        return self::PREFIX.$clinic->getKey();
    }

    public static function parseClinicId(string $name): ?int
    {
        if (! str_starts_with($name, self::PREFIX)) {
            return null;
        }

        $id = substr($name, strlen(self::PREFIX));

        return ctype_digit($id) ? (int) $id : null;
    }
}
