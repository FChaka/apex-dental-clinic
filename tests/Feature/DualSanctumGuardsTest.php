<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\StaffMember;
use Tests\TestCase;

class DualSanctumGuardsTest extends TestCase
{
    public function test_platform_admin_uses_central_connection(): void
    {
        $this->assertSame('central', (new PlatformAdmin)->getConnectionName());
    }

    public function test_staff_member_does_not_use_central_connection(): void
    {
        $this->assertNotSame('central', (new StaffMember)->getConnectionName());
    }

    public function test_auth_config_defines_session_guards_for_clinic_and_platform(): void
    {
        $this->assertSame('session', config('auth.guards.platform_session.driver'));
        $this->assertSame('platform_admins', config('auth.guards.platform_session.provider'));
        $this->assertSame('session', config('auth.guards.clinic_session.driver'));
        $this->assertSame('staff_members', config('auth.guards.clinic_session.provider'));
        $this->assertSame(PlatformAdmin::class, config('auth.providers.platform_admins.model'));
        $this->assertSame(StaffMember::class, config('auth.providers.staff_members.model'));
    }
}
