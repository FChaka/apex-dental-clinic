<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);
    $this->dentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);
    $this->otherDentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
    ]);
    $this->patient = Patient::factory()->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lists all appointments for admin', function () {
    $a1 = Appointment::factory()->create(['dentist_id' => $this->dentist->id]);
    $a2 = Appointment::factory()->create(['dentist_id' => $this->otherDentist->id]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments'));

    $response->assertOk()
        ->assertJsonPath('message', 'OK');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($a1->id)->toContain($a2->id);
});

it('scopes the appointment index to the acting dentist for staff', function () {
    $mine = Appointment::factory()->create(['dentist_id' => $this->dentist->id]);
    $otherAppointment = Appointment::factory()->create(['dentist_id' => $this->otherDentist->id]);

    $response = $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($otherAppointment->id);
});

it('does not return 403 when staff lists appointments but filters out other dentists', function () {
    Appointment::factory()->create(['dentist_id' => $this->otherDentist->id]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments'))
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('filters appointments by date_from and date_to', function () {
    Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-10',
    ]);
    Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-20',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments?date_from=2026-05-15&date_to=2026-05-25'));

    $response->assertOk();
    expect(collect($response->json('data')))->toHaveCount(1)
        ->and($response->json('data.0.date'))->toBe('2026-05-20');
});

it('filters appointments by status', function () {
    Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'status' => 'Upcoming',
    ]);
    Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'status' => 'Completed',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments?status=Completed'));

    $response->assertOk();
    expect(collect($response->json('data')))->toHaveCount(1)
        ->and($response->json('data.0.status'))->toBe('Completed');
});

it('filters appointments by patient_id', function () {
    $p2 = Patient::factory()->create();
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
    ]);
    Appointment::factory()->create([
        'patient_id' => $p2->id,
        'dentist_id' => $this->dentist->id,
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments?patient_id='.$this->patient->id));

    $response->assertOk();
    expect(collect($response->json('data')))->toHaveCount(1)
        ->and($response->json('data.0.patient_id'))->toBe($this->patient->id);
});

it('ignores dentist_id query filter for staff', function () {
    $mine = Appointment::factory()->create(['dentist_id' => $this->dentist->id]);
    $otherAppointment = Appointment::factory()->create(['dentist_id' => $this->otherDentist->id]);

    $response = $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments?dentist_id='.$this->otherDentist->id));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($otherAppointment->id);
});

it('creates an appointment', function () {
    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/appointments'), [
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'date' => '2026-05-01',
            'time' => '09:30',
            'treatment' => 'Cleaning',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.treatment', 'Cleaning')
        ->assertJsonPath('data.time', '09:30')
        ->assertJsonPath('data.status', 'Upcoming');

    expect(Appointment::query()->where('treatment', 'Cleaning')->exists())->toBeTrue();
});

it('rejects appointment at an already booked time', function () {
    Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-01',
        'time' => '10:00',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/appointments'), [
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'date' => '2026-05-01',
            'time' => '10:00',
            'treatment' => 'Cleaning',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This dentist already has an appointment at this time.');
});

it('returns 422 when store validation fails for a missing required field', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/appointments'), [
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'date' => '2026-05-01',
            'time' => '10:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['treatment']);
});

it('allows staff to update their own appointment', function () {
    $appointment = Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'treatment' => 'Checkup',
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/appointments/{$appointment->id}"), [
            'treatment' => 'Deep clean',
        ])
        ->assertOk()
        ->assertJsonPath('data.treatment', 'Deep clean');
});

it('returns 403 when staff updates another dentists appointment', function () {
    $appointment = Appointment::factory()->create([
        'dentist_id' => $this->otherDentist->id,
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/appointments/{$appointment->id}"), [
            'notes' => 'Nope',
        ])
        ->assertForbidden();
});

it('returns 422 when reschedule moves into a taken slot', function () {
    Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-01',
        'time' => '10:00',
    ]);
    $movable = Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-01',
        'time' => '11:00',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/appointments/{$movable->id}"), [
            'time' => '10:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This dentist already has an appointment at this time.');
});

it('allows updating appointment to its own current time slot', function () {
    $appointment = Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-01',
        'time' => '10:00',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/appointments/{$appointment->id}"), [
            'status' => 'Completed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Completed');
});

it('allows staff to delete their own appointment', function () {
    $appointment = Appointment::factory()->create([
        'dentist_id' => $this->dentist->id,
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/appointments/{$appointment->id}"))
        ->assertNoContent();

    expect(Appointment::query()->whereKey($appointment->id)->exists())->toBeFalse();
});

it('returns 403 when staff deletes another dentists appointment', function () {
    $appointment = Appointment::factory()->create([
        'dentist_id' => $this->otherDentist->id,
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/appointments/{$appointment->id}"))
        ->assertForbidden();

    expect(Appointment::query()->whereKey($appointment->id)->exists())->toBeTrue();
});

it('allows admin to delete any appointment', function () {
    $appointment = Appointment::factory()->create([
        'dentist_id' => $this->otherDentist->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/appointments/{$appointment->id}"))
        ->assertNoContent();
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments'))
        ->assertUnauthorized();
});

it('returns 400 when X-Tenant-Slug header is missing', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(['Referer' => tenantUrl($this->clinic, '/')])
        ->getJson(clinicApiUrl($this->clinic, 'api/appointments'))
        ->assertStatus(400)
        ->assertJsonPath('message', 'Missing X-Tenant-Slug header.');
});

it('cannot access appointments from another clinic', function () {
    $otherClinic = createTestTenant('other-clinic');
    tenancy()->initialize($otherClinic);
    $otherAppointment = Appointment::factory()->create();
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/appointments/{$otherAppointment->id}"), [
            'status' => 'Completed',
        ])
        ->assertNotFound();
});
