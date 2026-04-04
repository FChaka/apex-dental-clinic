<?php

declare(strict_types=1);

use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\StaffMember;
use App\Support\ClinicSanctumTokenBinding;

afterEach(function () {
    tenancy()->end();
});

it('returns a token on valid platform login', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $this->postJson('/api/platform/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ])->assertOk()
        ->assertJsonStructure([
            'data' => ['token', 'admin'],
            'message',
        ])
        ->assertJsonPath('data.admin.email', 'admin@example.com');
});

it('returns 401 on invalid platform credentials', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $this->postJson('/api/platform/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('data', null);
});

it('returns platform me for a valid platform bearer token', function () {
    $admin = PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $plain = $admin->createToken('platform')->plainTextToken;

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])->getJson('/api/platform/auth/me')
        ->assertOk()
        ->assertJsonPath('data.admin.email', 'admin@example.com');
});

it('rejects platform me with a clinic bearer token', function () {
    $clinic = createTestTenant();
    tenancy()->initialize($clinic);

    $staff = StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    $plain = $staff->createToken(ClinicSanctumTokenBinding::tokenNameForClinic($clinic))->plainTextToken;

    tenancy()->end();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])->getJson('/api/platform/auth/me')
        ->assertUnauthorized();

    dropTenantDatabaseIfExists($clinic);
});

it('returns 422 when platform login validation fails', function () {
    $this->postJson('/api/platform/auth/login', [
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
