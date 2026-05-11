<?php

declare(strict_types=1);

use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\StaffMember;

afterEach(function () {
    tenancy()->end();
});

beforeEach(function () {
    $this->withCredentials();
});

it('returns admin on valid platform login without token', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret123!',
        ])->assertOk()
        ->assertJsonMissingPath('data.token')
        ->assertJsonStructure([
            'data' => ['admin'],
            'message',
        ])
        ->assertJsonPath('data.admin.email', 'admin@example.com');

    $this->assertAuthenticated('platform_session');
});

it('returns 401 on invalid platform credentials', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
        ->assertJsonPath('data', null);
});

it('returns platform me when session cookie is present', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $login = $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret123!',
        ]);

    $login->assertOk();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(platformStatefulHeaders())
        ->getJson('/api/platform/auth/me')
        ->assertOk()
        ->assertJsonPath('data.admin.email', 'admin@example.com');
});

it('rejects platform me when only clinic_session is authenticated', function () {
    $clinic = createTestTenant();
    tenancy()->initialize($clinic);

    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    $login = $this->withHeaders(clinicStatefulHeaders($clinic))
        ->postJson(clinicApiUrl($clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '1111',
        ]);

    $login->assertOk();

    tenancy()->end();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(platformStatefulHeaders())
        ->getJson('/api/platform/auth/me')
        ->assertUnauthorized();

    dropTenantDatabaseIfExists($clinic);
});

it('logs out and clears platform_session', function () {
    PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Secret123!',
    ]);

    $login = $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret123!',
        ]);

    $login->assertOk();

    $cookies = sessionCookiesFromResponse($login);
    $sessionId = $cookies[config('session.cookie')] ?? null;

    expect($sessionId)->not->toBeEmpty();

    $this->withCredentials()
        ->withCookies($cookies)
        ->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/logout')
        ->assertOk();

    $this->assertGuest('platform_session');
});

it('returns 422 when platform login validation fails', function () {
    $this->withHeaders(platformStatefulHeaders())
        ->postJson('/api/platform/auth/login', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('clinic staff session cannot access platform endpoints', function () {
    $clinic = createTestTenant();
    tenancy()->initialize($clinic);

    StaffMember::factory()->create([
        'username' => 'staffuser',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    $login = $this->withHeaders(clinicStatefulHeaders($clinic))
        ->postJson(clinicApiUrl($clinic, 'api/auth/login'), [
            'username' => 'staffuser',
            'pin' => '1111',
        ]);

    $login->assertOk();
    tenancy()->end();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(platformStatefulHeaders())
        ->getJson('/api/platform/clinics')
        ->assertUnauthorized();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(platformStatefulHeaders())
        ->getJson('/api/platform/overview')
        ->assertUnauthorized();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(platformStatefulHeaders())
        ->getJson('/api/platform/subscriptions')
        ->assertUnauthorized();

    $this->withCredentials()
        ->withCookies(sessionCookiesFromResponse($login))
        ->withHeaders(platformStatefulHeaders())
        ->getJson('/api/platform/spendings')
        ->assertUnauthorized();
});

it('platform admin session cannot access clinic endpoints', function () {
    $clinic = createTestTenant();

    $admin = PlatformAdmin::query()->create([
        'name' => 'PA',
        'email' => 'pa@example.com',
        'password' => 'Secret123!',
    ]);

    $this->actingAs($admin, 'platform_session');

    $this->withHeaders(clinicStatefulHeaders($clinic))
        ->getJson(clinicApiUrl($clinic, 'api/auth/me'))
        ->assertUnauthorized();
});
