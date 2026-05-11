<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\StaffMember;
use Illuminate\Support\Facades\Storage;

final class StaffAvatarUrl
{
    /**
     * Resolvable URL for <img src>.
     *
     * - If the avatar exists on the tenant public disk, return a static `/storage/...` URL.
     * - Else if the avatar exists on the default (tenant-local/private) disk, return the authenticated API stream endpoint.
     */
    public static function forStaffMember(StaffMember $staff): ?string
    {
        $path = $staff->avatar_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $public = Storage::disk('public');
        if ($public->exists($path)) {
            return $public->url($path);
        }

        $defaultDisk = (string) config('filesystems.default');
        if ($defaultDisk !== '' && Storage::disk($defaultDisk)->exists($path)) {
            $tenantSlug = (string) (tenancy()->tenant?->slug ?? '');

            return route('api.staff.avatar', [
                'staff' => $staff->id,
                'tenant' => $tenantSlug,
            ], true);
        }

        return null;
    }
}
