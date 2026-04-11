<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\StaffMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Twilio\Rest\Client as TwilioClient;

final class SwitchStaffOtpService
{
    public function __construct(
        private readonly TwilioClient $twilio,
    ) {}

    public function generate(StaffMember $staff, string $tenantSlug): void
    {
        $plain = str_pad((string) random_int(0, 9_999), 4, '0', STR_PAD_LEFT);

        $key = "switch_otp_{$tenantSlug}_{$staff->id}";

        Cache::put($key, Hash::make($plain), now()->addMinutes(10));

        $phone = (string) $staff->phone;

        $this->twilio->messages->create($phone, [
            'from' => config('services.twilio.from'),
            'body' => "Your 4-digit switch PIN is: {$plain}. Valid for 10 minutes.",
        ]);
    }

    public function verify(StaffMember $staff, string $tenantSlug, string $otp): bool
    {
        $key = "switch_otp_{$tenantSlug}_{$staff->id}";

        $hash = Cache::get($key);

        if (! is_string($hash) || $hash === '') {
            return false;
        }

        if (! Hash::check($otp, $hash)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }
}
