<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ConfigureSanctumPersonalAccessTokenModel;
use App\Models\Central\PersonalAccessToken as CentralPersonalAccessToken;
use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\PersonalAccessToken as TenantPersonalAccessToken;
use App\Models\Tenant\StaffMember;
use App\Support\ClinicSanctumTokenBinding;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
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

    public function test_staff_member_tokens_relation_uses_tenant_personal_access_token_model(): void
    {
        $staff = new StaffMember;
        $this->assertSame(TenantPersonalAccessToken::class, $staff->tokens()->getRelated()::class);
    }

    public function test_platform_admin_tokens_relation_uses_central_personal_access_token_model(): void
    {
        $admin = new PlatformAdmin;
        $this->assertSame(CentralPersonalAccessToken::class, $admin->tokens()->getRelated()::class);
    }

    public function test_configure_sanctum_pat_middleware_restores_previous_token_model(): void
    {
        $previous = Sanctum::$personalAccessTokenModel;
        $middleware = new ConfigureSanctumPersonalAccessTokenModel;
        $middleware->handle(Request::create('/'), function () {
            return response('ok');
        }, 'platform');
        $this->assertSame($previous, Sanctum::$personalAccessTokenModel);
    }

    public function test_configure_sanctum_pat_middleware_sets_platform_model_during_request(): void
    {
        $previous = Sanctum::$personalAccessTokenModel;
        $middleware = new ConfigureSanctumPersonalAccessTokenModel;
        $observed = null;
        $middleware->handle(Request::create('/'), function () use (&$observed) {
            $observed = Sanctum::$personalAccessTokenModel;

            return response('ok');
        }, 'platform');
        $this->assertSame(CentralPersonalAccessToken::class, $observed);
        $this->assertSame($previous, Sanctum::$personalAccessTokenModel);
    }

    public function test_clinic_sanctum_token_binding_parse_clinic_id(): void
    {
        $this->assertSame(12, ClinicSanctumTokenBinding::parseClinicId('clinic:12'));
        $this->assertNull(ClinicSanctumTokenBinding::parseClinicId('wrong'));
        $this->assertNull(ClinicSanctumTokenBinding::parseClinicId('clinic:'));
    }

    public function test_auth_config_defines_isolated_platform_and_clinic_guards(): void
    {
        $this->assertSame('sanctum', config('auth.guards.platform.driver'));
        $this->assertSame('platform_admins', config('auth.guards.platform.provider'));
        $this->assertSame('sanctum', config('auth.guards.clinic.driver'));
        $this->assertSame('staff_members', config('auth.guards.clinic.provider'));
        $this->assertSame(PlatformAdmin::class, config('auth.providers.platform_admins.model'));
        $this->assertSame(StaffMember::class, config('auth.providers.staff_members.model'));
    }
}
