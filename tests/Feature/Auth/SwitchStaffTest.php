<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\StaffMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Twilio\Rest\Client as TwilioClient;

function seedOtp(int $staffId, string $plain, bool $expired = false): void
{
    $key = "switch_otp_test-clinic_{$staffId}";
    $ttl = $expired ? now()->subMinute() : now()->addMinutes(10);
    Cache::put($key, Hash::make($plain), $ttl);
}

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->actingStaff = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);

    $this->targetStaff = StaffMember::factory()->create([
        'phone' => '+38344123456',
    ]);

    $this->mock(TwilioClient::class, function ($mock) {
        $mock->messages = Mockery::mock();
        $mock->messages->shouldReceive('create')->andReturn(true);
    });
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('sends a switch-staff OTP request successfully without returning an OTP', function () {
    $response = $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetStaff->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Verification code sent to the staff member\'s phone.')
        ->assertJsonPath('data', null);

    expect($response->getContent())->not->toMatch('/Your 4-digit switch PIN is: \d{4}/');
});

it('returns 404 when target staff is not found on request', function () {
    $missingId = (int) (StaffMember::query()->max('id') ?? 0) + 99_999;

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $missingId,
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Staff member not found.');
});

it('returns 422 when target staff has no phone on request', function () {
    $noPhone = StaffMember::factory()->create([
        'phone' => null,
    ]);

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $noPhone->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This staff member has no registered phone number.');
});

it('returns 500 when Twilio fails to send', function () {
    $throwing = Mockery::mock(TwilioClient::class);
    $messages = Mockery::mock();
    $messages->shouldReceive('create')->once()->andThrow(new RuntimeException('Twilio unavailable'));
    $throwing->messages = $messages;
    $this->app->instance(TwilioClient::class, $throwing);

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetStaff->id,
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to send verification code. Please try again.');
});

it('switches the session to the target staff after valid OTP', function () {
    $plain = '1234';
    seedOtp($this->targetStaff->id, $plain);

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $this->targetStaff->id,
            'otp' => $plain,
        ])
        ->assertOk()
        ->assertJsonPath('data.staff.id', $this->targetStaff->id)
        ->assertJsonMissingPath('data.token');
});

it('returns 422 for a wrong OTP', function () {
    seedOtp($this->targetStaff->id, '1234');

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $this->targetStaff->id,
            'otp' => '0000',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid or expired code.');
});

it('rejects an expired OTP', function () {
    seedOtp($this->targetStaff->id, '1234', expired: true);

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $this->targetStaff->id,
            'otp' => '1234',
        ])
        ->assertUnprocessable();
});

it('rejects an OTP that has already been used', function () {
    $plain = '1234';
    seedOtp($this->targetStaff->id, $plain);

    $headers = clinicStatefulHeaders($this->clinic);

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders($headers)
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $this->targetStaff->id,
            'otp' => $plain,
        ])
        ->assertOk();

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders($headers)
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $this->targetStaff->id,
            'otp' => $plain,
        ])
        ->assertUnprocessable();
});

it('rejects an OTP seeded for a different tenant', function () {
    $plain = '1234';

    Cache::put(
        "switch_otp_other-clinic_{$this->targetStaff->id}",
        Hash::make($plain),
        now()->addMinutes(10)
    );

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $this->targetStaff->id,
            'otp' => $plain,
        ])
        ->assertUnprocessable();
});

it('returns 401 for unauthenticated switch-staff request', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetStaff->id,
        ])
        ->assertUnauthorized();
});

it('returns 400 when X-Tenant-Slug is missing on switch-staff routes', function () {
    $this->withHeaders(['Referer' => tenantUrl($this->clinic, '/')])
        ->actingAs($this->actingStaff, 'clinic_session')
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetStaff->id,
        ])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Missing X-Tenant-Slug header.');
});

it('returns 404 when X-Tenant-Slug does not match a clinic', function () {
    $this->withHeaders([
        'Referer' => tenantUrl($this->clinic, '/'),
        'X-Tenant-Slug' => 'nonexistent-clinic-slug',
    ])
        ->actingAs($this->actingStaff, 'clinic_session')
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff'), [
            'target_staff_id' => $this->targetStaff->id,
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Clinic not found.');
});

it('returns 404 when target staff is not found on verify', function () {
    $missingId = (int) (StaffMember::query()->max('id') ?? 0) + 99_999;

    $this->actingAs($this->actingStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/auth/switch-staff/verify'), [
            'target_staff_id' => $missingId,
            'otp' => '1234',
        ])
        ->assertNotFound();
});
