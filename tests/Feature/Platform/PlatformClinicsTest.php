<?php

declare(strict_types=1);

use App\Jobs\SendOwnerWelcomeEmail;
use App\Models\Central\Clinic;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformService;
use App\Models\Tenant\StaffMember;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withCredentials();
    $this->admin = PlatformAdmin::factory()->create();
    $this->actingAs($this->admin, 'platform_session');
    $this->withHeaders(platformStatefulHeaders());
});

it('lists clinics', function () {
    Clinic::factory()->count(3)->create();

    $response = $this->getJson('/api/platform/clinics');

    $response->assertOk();
    expect($response->json('data'))->toBeArray();
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(3);
});

it('creates a clinic and provisions tenant DB', function () {
    Mail::fake();
    Queue::fake();

    $response = $this->postJson('/api/platform/clinics', [
        'name' => 'Test Dental',
        'slug' => 'test-dental',
        'contact_email' => 'info@testdental.com',
        'plan' => 'Starter',
        'seats' => 5,
        'owner_name' => 'Dr. Test Owner',
        'owner_username' => 'dr.test.owner',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('clinics', ['slug' => 'test-dental'], 'central');
    $this->assertDatabaseHas('domains', ['domain' => 'test-dental'], 'central');

    $clinic = Clinic::query()->where('slug', 'test-dental')->first();
    expect($clinic)->not->toBeNull();

    $clinic->run(function () {
        $this->assertDatabaseCount('clinic_schedules', 7);
        $this->assertDatabaseHas('clinic_settings', ['clinic_name' => 'Test Dental']);

        $owner = StaffMember::query()->where('username', 'dr.test.owner')->first();
        expect($owner)->not->toBeNull();
        expect($owner->clinic_access_level)->toBe('super_admin');
        expect($owner->sign_in_method)->toBe('pin');
        expect($owner->pin_length)->toBe(6);
        expect($owner->login_pin)->not->toBeNull();
        expect($owner->must_change_credentials)->toBeTrue();
        expect($owner->temp_pin_expires_at)->not->toBeNull();
        expect(\Carbon\Carbon::parse((string) $owner->temp_pin_expires_at)->greaterThan(now()))->toBeTrue();
    });

    $this->assertDatabaseHas('audit_log', ['action' => 'clinic.created'], 'central');

    Queue::assertPushed(SendOwnerWelcomeEmail::class, function (SendOwnerWelcomeEmail $job) {
        return $job->contactEmail === 'info@testdental.com'
            && $job->username === 'dr.test.owner';
    });

    $response->assertJsonMissing(['temporaryPin']);
    $response->assertJsonMissing(['temporary_pin']);
    $response->assertJsonMissing(['login_pin']);
});

it('owner can log in with temporary PIN before expiry', function () {
    $clinic = createTestTenant();
    $clinic->run(function () {
        StaffMember::query()->create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'username' => 'owner',
            'role' => 'Dentist',
            'clinic_access_level' => 'super_admin',
            'sign_in_method' => 'pin',
            'pin_length' => 6,
            'login_pin' => '123456',
            'temp_pin_expires_at' => now()->addHours(24),
            'must_change_credentials' => true,
        ]);
    });

    $this->withHeaders(clinicStatefulHeaders($clinic))
        ->postJson(clinicApiUrl($clinic, 'api/auth/login'), [
            'username' => 'owner',
            'pin' => '123456',
        ])
        ->assertOk()
        ->assertJsonPath('data.must_change_credentials', true);
});

it('owner cannot log in with expired temporary PIN', function () {
    $clinic = createTestTenant();
    $clinic->run(function () {
        StaffMember::query()->create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'username' => 'owner',
            'role' => 'Dentist',
            'clinic_access_level' => 'super_admin',
            'sign_in_method' => 'pin',
            'pin_length' => 6,
            'login_pin' => '123456',
            'temp_pin_expires_at' => now()->subHour(),
            'must_change_credentials' => true,
        ]);
    });

    $this->withHeaders(clinicStatefulHeaders($clinic))
        ->postJson(clinicApiUrl($clinic, 'api/auth/login'), [
            'username' => 'owner',
            'pin' => '123456',
        ])
        ->assertUnauthorized();
});

it('rejects duplicate slug', function () {
    Clinic::factory()->create(['slug' => 'taken']);

    $this->postJson('/api/platform/clinics', [
        'name' => 'Dup Clinic',
        'slug' => 'taken',
        'contact_email' => 'dup@test.com',
        'plan' => 'Starter',
        'owner_name' => 'Owner',
        'owner_username' => 'owner',
    ])->assertUnprocessable();
});

it('soft-deletes a clinic', function () {
    $clinic = Clinic::factory()->create();

    $this->deleteJson("/api/platform/clinics/{$clinic->id}")
        ->assertOk();

    expect($clinic->fresh()->deleted_at)->not->toBeNull();
});

it('rejects unauthenticated requests', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/platform/clinics')->assertUnauthorized();
});

it('resends owner pin and audits', function () {
    Mail::fake();
    Queue::fake();

    $this->postJson('/api/platform/clinics', [
        'name' => 'Resend Clinic',
        'slug' => 'resend-clinic',
        'contact_email' => 'owner@resend.com',
        'plan' => 'Starter',
        'owner_name' => 'Owner',
        'owner_username' => 'ownerlogin',
    ])->assertCreated();

    $clinic = Clinic::query()->where('slug', 'resend-clinic')->firstOrFail();

    Queue::fake();

    $this->postJson("/api/platform/clinics/{$clinic->id}/resend-owner-pin")
        ->assertOk();

    $this->assertDatabaseHas('audit_log', ['action' => 'clinic.owner_pin_resent'], 'central');
    Queue::assertPushed(SendOwnerWelcomeEmail::class, 1);
});

it('manages clinic services', function () {
    $clinic = Clinic::factory()->create();
    $service = PlatformService::factory()->create();

    $this->postJson("/api/platform/clinics/{$clinic->id}/services", [
        'service_id' => $service->id,
    ])->assertCreated();

    $this->getJson("/api/platform/clinics/{$clinic->id}/services")
        ->assertOk()
        ->assertJsonPath('data.0.service_id', $service->id);
});
