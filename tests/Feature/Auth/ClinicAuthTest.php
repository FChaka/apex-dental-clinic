<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\PersonalAccessToken;
use App\Models\Tenant\StaffMember;
use App\Support\ClinicSanctumTokenBinding;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->clinic = createTestTenant();
    tenancy()->initialize($this->clinic);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns a token and staff on valid clinic PIN login', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson(tenantUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
            'pin' => '4242',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['token', 'staff', 'permissions'],
            'message',
        ])
        ->assertJsonPath('data.staff.username', 'alice');
});

it('returns 401 on invalid clinic PIN', function () {
    StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->withHeaders(['Accept' => 'application/json'])
        ->postJson(tenantUrl($this->clinic, 'api/auth/login'), [
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

    $this->withHeaders(['Accept' => 'application/json'])
        ->postJson(tenantUrl($this->clinic, 'api/auth/login'), [
            'username' => 'alice',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['pin']);
});

it('returns 422 when password staff omits password', function () {
    StaffMember::factory()->passwordSignIn('secret-pass')->create([
        'username' => 'bob',
    ]);

    $this->withHeaders(['Accept' => 'application/json'])
        ->postJson(tenantUrl($this->clinic, 'api/auth/login'), [
            'username' => 'bob',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns a token on valid password login', function () {
    StaffMember::factory()->passwordSignIn('secret-pass')->create([
        'username' => 'bob',
    ]);

    $this->withHeaders(['Accept' => 'application/json'])
        ->postJson(tenantUrl($this->clinic, 'api/auth/login'), [
            'username' => 'bob',
            'password' => 'secret-pass',
        ])->assertOk()
        ->assertJsonPath('data.staff.username', 'bob');
});

it('returns clinic me for a valid clinic bearer token', function () {
    $staff = StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    $plain = $staff->createToken(ClinicSanctumTokenBinding::tokenNameForClinic($this->clinic))->plainTextToken;

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])->getJson(tenantUrl($this->clinic, 'api/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.staff.username', 'alice');
});

it('rejects clinic routes with a platform bearer token', function () {
    $admin = PlatformAdmin::query()->create([
        'name' => 'Admin',
        'email' => 'plat@example.com',
        'password' => 'Secret123!',
    ]);

    $plain = $admin->createToken('platform')->plainTextToken;

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])->getJson(tenantUrl($this->clinic, 'api/auth/me'))
        ->assertUnauthorized();
});

it('deletes the personal access token on clinic logout', function () {
    $staff = StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $plain = $staff->createToken(ClinicSanctumTokenBinding::tokenNameForClinic($this->clinic))->plainTextToken;

    expect(PersonalAccessToken::query()->count())->toBe(1);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])->postJson(tenantUrl($this->clinic, 'api/auth/logout'))
        ->assertOk();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('issues a new token on switch-staff and me reflects the target staff', function () {
    $alice = StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    $bob = StaffMember::factory()->create([
        'username' => 'bob',
        'login_pin' => bcrypt('8888'),
        'sign_in_method' => 'pin',
    ]);

    $alicePlain = $alice->createToken(ClinicSanctumTokenBinding::tokenNameForClinic($this->clinic))->plainTextToken;

    expect(PersonalAccessToken::query()->count())->toBe(1);

    $switch = $this->withHeaders([
        'Authorization' => 'Bearer '.$alicePlain,
        'Accept' => 'application/json',
    ])->postJson(tenantUrl($this->clinic, 'api/auth/switch-staff'), [
        'username' => 'bob',
        'pin' => '8888',
    ]);

    $switch->assertOk()
        ->assertJsonStructure([
            'data' => ['token', 'staff', 'permissions'],
            'message',
        ])
        ->assertJsonPath('data.staff.username', 'bob');

    $bobPlain = $switch->json('data.token');
    expect($bobPlain)->not->toBe($alicePlain);
    expect(PersonalAccessToken::query()->count())->toBe(1);

    $remaining = PersonalAccessToken::query()->first();
    expect($remaining)->not->toBeNull();
    expect((int) $remaining->tokenable_id)->toBe((int) $bob->id);

    // Sanctum uses RequestGuard: user() is cached per guard instance across $this->call() in the
    // same PHP process. Forget guards so the next request re-resolves from the Bearer header.
    Auth::forgetGuards();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$bobPlain,
        'Accept' => 'application/json',
    ])->getJson(tenantUrl($this->clinic, 'api/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.staff.username', 'bob');

    Auth::forgetGuards();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$alicePlain,
        'Accept' => 'application/json',
    ])->getJson(tenantUrl($this->clinic, 'api/auth/me'))
        ->assertUnauthorized();
});

it('returns 401 when switch-staff PIN is wrong', function () {
    $alice = StaffMember::factory()->create([
        'username' => 'alice',
        'login_pin' => bcrypt('1111'),
        'sign_in_method' => 'pin',
    ]);

    StaffMember::factory()->create([
        'username' => 'bob',
        'login_pin' => bcrypt('8888'),
        'sign_in_method' => 'pin',
    ]);

    $plain = $alice->createToken(ClinicSanctumTokenBinding::tokenNameForClinic($this->clinic))->plainTextToken;

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])->postJson(tenantUrl($this->clinic, 'api/auth/switch-staff'), [
        'username' => 'bob',
        'pin' => '0000',
    ])->assertUnauthorized();
});
