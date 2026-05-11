<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-05-15 12:00:00', 'UTC'));
    $this->clinic = createTestTenant('weekly-appt-'.uniqid());
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->dentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);
    $this->receptionistStaff = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);
    $this->patient = Patient::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns seven weekday buckets for current week excluding non clinical assignees', function () {
    // Week of 2026-05-15: Mon 2026-05-11 ... Sun 2026-05-17
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-11',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-12',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-12',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->receptionistStaff->id,
        'date' => '2026-05-12',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/weekly-appointments'));

    $response->assertOk();
    $days = $response->json('data.days');
    expect($days)->toHaveCount(7)
        ->and($response->json('data.week_start'))->toBe('2026-05-11')
        ->and($response->json('data.week_end'))->toBe('2026-05-17');

    $byDate = collect($days)->keyBy('date');
    expect($byDate['2026-05-11']['count'])->toBe(1)
        ->and($byDate['2026-05-12']['count'])->toBe(2);
});

it('selects past week via week_start', function () {
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-06',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/weekly-appointments?week_start=2026-05-08'))
        ->assertOk()
        ->assertJsonPath('data.week_start', '2026-05-04')
        ->assertJsonPath('data.days.2.count', 1);
});

it('lets receptionist see all clinical appointments in the range', function () {
    $other = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);

    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'date' => '2026-05-14',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $other->id,
        'date' => '2026-05-14',
    ]);

    $response = $this->actingAs($this->receptionistStaff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/weekly-appointments'));

    $byDate = collect($response->json('data.days'))->keyBy('date');
    expect($byDate['2026-05-14']['count'])->toBe(2);
});
