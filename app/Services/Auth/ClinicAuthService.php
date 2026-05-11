<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Tenant\StaffMember;
use Illuminate\Support\Facades\Hash;

final class ClinicAuthService
{
    public function findStaffByUsername(string $username): ?StaffMember
    {
        return StaffMember::query()->where('username', $username)->first();
    }

    public function verifyCredentials(StaffMember $staff, ?string $pin, ?string $password): bool
    {
        return match ($staff->sign_in_method) {
            'pin' => $pin !== null && $pin !== '' && Hash::check($pin, (string) $staff->getRawOriginal('login_pin')),
            'password' => $password !== null && $password !== '' && Hash::check($password, (string) $staff->getRawOriginal('login_password')),
            default => false,
        };
    }

    public function verifyPinForStaff(StaffMember $staff, string $pin): bool
    {
        return Hash::check($pin, (string) $staff->getRawOriginal('login_pin'));
    }
}
