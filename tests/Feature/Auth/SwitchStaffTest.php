<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\StaffMember;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->actingStaff = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);

    $this->targetPinStaff = StaffMember::factory()->create([
        'login_pin' => bcrypt('4242'),
        'sign_in_method' => 'pin',
    ]);

    $this->targetPasswordStaff = StaffMember::factory()->passwordSignIn('secret-password')->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('switches session when target uses PIN and credential is valid', function () {
    $response = $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPinStaff->id,
            'credential' => '4242',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.staff.id', $this->targetPinStaff->id)
        ->assertJsonPath('message', 'Staff switched successfully.')
        ->assertJsonMissingPath('data.staff.login_pin')
        ->assertJsonMissingPath('data.staff.login_password')
        ->assertJsonStructure(['data' => ['staff', 'permissions'], 'message']);

    $this->assertAuthenticatedAs($this->targetPinStaff, 'clinic_session');

    $body = $response->getContent();
    expect($body)->not->toContain('login_pin')
        ->and($body)->not->toContain('login_password');
});

it('switches session when target uses password and credential is valid', function () {
    $response = $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPasswordStaff->id,
            'credential' => 'secret-password',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.staff.id', $this->targetPasswordStaff->id);

    $this->assertAuthenticatedAs($this->targetPasswordStaff, 'clinic_session');
});

it('returns 422 for wrong PIN', function () {
    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPinStaff->id,
            'credential' => '0000',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('returns 422 for wrong password', function () {
    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPasswordStaff->id,
            'credential' => 'wrong-password',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('returns 404 when target staff is not found', function () {
    $missingId = (int) (StaffMember::query()->max('id') ?? 0) + 99_999;

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $missingId,
            'credential' => '4242',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Staff member not found.');
});

it('returns 422 when credential field is missing', function () {
    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPinStaff->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credential']);
});

it('returns 401 for unauthenticated switch-staff request', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPinStaff->id,
            'credential' => '4242',
        ])
        ->assertUnauthorized();
});

it('returns 400 when X-Tenant-Slug is missing on switch-staff', function () {
    $this->withHeaders(['Referer' => tenantUrl($this->clinic, '/')])
        ->actingAs($this->actingStaff, 'clinic_session')
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetPinStaff->id,
            'credential' => '4242',
        ])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Missing X-Tenant-Slug header.');
});

it('returns 404 when target staff id exists only in another tenant database', function () {
    $maxInA = (int) StaffMember::query()->max('id');

    $otherClinic = createTestTenant('other-switch-'.str_replace('.', '', uniqid('', true)));
    tenancy()->initialize($otherClinic);
    StaffMember::factory()->count($maxInA + 1)->create();
    $foreignOnlyId = (int) StaffMember::query()->max('id');
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    expect(StaffMember::query()->whereKey($foreignOnlyId)->exists())->toBeFalse();

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $foreignOnlyId,
            'credential' => 'irrelevant',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Staff member not found.');
});
