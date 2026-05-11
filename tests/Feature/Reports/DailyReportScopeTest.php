<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-05-15 12:00:00', 'UTC'));
    $this->clinic = createTestTenant('daily-scope-'.uniqid());
    tenancy()->initialize($this->clinic);

    $this->receptionist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);
    $this->dentistDrX = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'name' => 'Dr X',
    ]);
    $this->dentistDrY = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'name' => 'Dr Y',
    ]);
    $this->nurse = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dental Nurse',
    ]);
    $this->patient = Patient::factory()->create();

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistDrX->id,
        'name' => 'A',
        'date' => '2026-02-10',
        'duration_minutes' => 20,
        'payment_status' => 'Paid',
        'price' => 50,
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistDrY->id,
        'name' => 'B',
        'date' => '2026-02-10',
        'duration_minutes' => 20,
        'payment_status' => 'Paid',
        'price' => 70,
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->nurse->id,
        'name' => 'N',
        'date' => '2026-02-10',
        'duration_minutes' => 20,
        'payment_status' => 'Paid',
        'price' => 10,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('receptionist sees all rows when unfiltered and empty by_staff when drilling into a dentist', function () {
    $base = clinicApiUrl($this->clinic, 'api/reports/daily?date=2026-02-10');

    $all = $this->actingAs($this->receptionist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson($base);

    $all->assertOk();
    expect($all->json('data.rows'))->toHaveCount(3)
        ->and($all->json('data.by_staff'))->not->toBe([]);

    $filtered = $this->actingAs($this->receptionist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson($base.'&dentist_id='.$this->dentistDrX->id);

    $filtered->assertOk();
    expect($filtered->json('data.rows'))->toHaveCount(1)
        ->and($filtered->json('data.by_staff'))->toBe([]);
});

it('dentist without dentist_id is scoped to own rows', function () {
    $this->actingAs($this->dentistDrX, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/daily?date=2026-02-10'))
        ->assertOk()
        ->assertJsonPath('data.rows.0.dentist_id', $this->dentistDrX->id);
});

it('allows dentist to pass own dentist_id explicitly', function () {
    $this->actingAs($this->dentistDrX, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/daily?date=2026-02-10&dentist_id='.$this->dentistDrX->id))
        ->assertOk()
        ->assertJsonPath('data.rows.0.dentist_id', $this->dentistDrX->id);
});

it('forbids dentist from requesting another dentist daily scope', function () {
    $this->actingAs($this->dentistDrX, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/daily?date=2026-02-10&dentist_id='.$this->dentistDrY->id))
        ->assertForbidden();
});

it('forces nurse to own treatment rows', function () {
    $this->actingAs($this->nurse, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/daily?date=2026-02-10'))
        ->assertOk()
        ->assertJsonPath('data.rows.0.dentist_id', $this->nurse->id);
});
