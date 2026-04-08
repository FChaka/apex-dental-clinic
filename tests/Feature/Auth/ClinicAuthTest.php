<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\StaffMember;

beforeEach(function () {
    $this->clinic = createTestTenant();
    tenancy()->initialize($this->clinic);
    $this->withCredentials();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns staff and permissions on valid clinic PIN login without token', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $response = $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '4242',
        ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.token')
        ->assertJsonStructure([
            'data' => ['staff', 'permissions'],
            'message',
        ])
        ->assertJsonPath('data.staff.username', 'alice');

    $this->assertAuthenticated('clinic_session');
});

it('returns 401 on invalid clinic PIN', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '9999',
        ])->assertUnauthorized()
        ->assertJsonPath('data', null);
});

it('returns 422 when PIN staff omits PIN', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['pin']);
});

it('returns 422 when password staff omits password', function () {
    StaffMember::factory()->passwordSignIn('secret-pass')->create([
        'username' => 'bob',
    ]);

    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'bob',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns staff on valid password login without token', function () {
    StaffMember::factory()->passwordSignIn('secret-pass')->create([
        'username' => 'bob',
    ]);

    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'bob',
            'password' => 'secret-pass',
        ])->assertOk()
        ->assertJsonMissingPath('data.token')
        ->assertJsonPath('data.staff.username', 'bob');
});

it('returns clinic me when session cookie is present', function () {
    $staff = StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    $login = $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '1111',
        ]);

    $login->assertOk();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.staff.username', 'alice');
});

it('rejects clinic routes when only platform_session is authenticated', function () {
    $admin = PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'plat@example.com',
        'password' => 'Secret123!',
    ]);

    $login = $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'plat@example.com',
            'password' => 'Secret123!',
        ]);

    $login->assertOk();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/auth/me'))
        ->assertUnauthorized();
});

it('logs out and clears clinic_session', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $login = $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '4242',
        ]);

    $login->assertOk();

    $cookies = sessionCookiesFromResponse($login);

    $this->withCredentials()
        ->withCookies($cookies)
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/logout'))
        ->assertOk();

    $this->assertGuest('clinic_session');

    $this->withCredentials()
        ->withCookies($cookies)
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/auth/me'))
        ->assertUnauthorized();
});

it('switch-staff updates session and me returns target staff', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    StaffMember::factory()->create([
        'username' => 'bob',
        'login_pin' => bcrypt('8888'),
        'sign_in_method' => 'pin',
    ]);

    $login = $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '1111',
        ]);

    $login->assertOk();
    $cookies = sessionCookiesFromResponse($login);

    $switch = $this->withCredentials()
        ->withCookies($cookies)
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'username' => 'bob',
            'pin' => '8888',
        ]);

    $switch->assertOk()
        ->assertJsonMissingPath('data.token')
        ->assertJsonStructure([
            'data' => ['staff', 'permissions'],
            'message',
        ])
        ->assertJsonPath('data.staff.username', 'bob');

    $cookiesAfterSwitch = sessionCookiesFromResponse($switch);

    $this->withCredentials()
        ->withCookies($cookiesAfterSwitch)
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.staff.username', 'bob');
});

it('returns 401 when switch-staff PIN is wrong', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    StaffMember::factory()->create([
        'username' => 'bob',
        'login_pin' => bcrypt('8888'),
        'sign_in_method' => 'pin',
    ]);

    $login = $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '1111',
        ]);

    $login->assertOk();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'username' => 'bob',
            'pin' => '0000',
        ])->assertUnauthorized();
});

it('returns 400 when X-Tenant-Slug header is missing on clinic routes', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->withHeaders(['Referer' => tenantUrl($this->clinic, '/')])
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '4242',
        ])->assertStatus(400)
        ->assertJsonPath('message', 'Missing X-Tenant-Slug header.');
});

it('returns 404 when X-Tenant-Slug does not match a clinic', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->withHeaders([
        'Referer' => tenantUrl($this->clinic, '/'),
        'X-Tenant-Slug' => 'nonexistent-clinic-slug',
    ])->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
        'username' => 'alice',
        'pin' => '4242',
    ])->assertNotFound()
        ->assertJsonPath('message', 'Clinic not found.');
});

it('sets session cookie on valid clinic login with X-Tenant-Slug', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '4242',
        ])->assertOk()
        ->assertCookie(config('session.cookie'));
});

it('allows platform login without X-Tenant-Slug', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'platform-no-header@example.com',
        'password' => 'Secret123!',
    ]);

    $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'platform-no-header@example.com',
            'password' => 'Secret123!',
        ])->assertOk();
});
