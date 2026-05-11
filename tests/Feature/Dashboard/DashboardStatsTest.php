<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-05-15 12:00:00', 'UTC'));
    $this->clinic = createTestTenant('dash-stats-'.uniqid());
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
        'role' => 'Dentist',
    ]);
    $this->dentistA = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'name' => 'Dr A',
    ]);
    $this->dentistB = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'name' => 'Dr B',
    ]);
    $this->nurse = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dental Nurse',
    ]);
    $this->receptionist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);
    $this->patient = Patient::factory()->create();

    Patient::factory()->count(2)->create();
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lets practice admin see clinic wide stats', function () {
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'date' => '2026-05-15',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'date' => '2026-05-15',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->receptionist->id,
        'date' => '2026-05-15',
    ]);

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Pending',
        'price' => 50,
        'date' => '2026-05-10',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'payment_status' => 'Paid',
        'price' => 200,
        'date' => '2026-05-12',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Pending',
        'price' => 999,
        'date' => '2026-05-14',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Pending',
        'price' => 0,
        'date' => '2026-05-14',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/stats'));

    $response->assertOk();
    expect($response->json('data.total_patients'))->toBe(3)
        ->and($response->json('data.todays_appointments'))->toBe(2)
        ->and($response->json('data.pending_treatments'))->toBe(2)
        ->and($response->json('data.monthly_revenue'))->toEqualWithDelta(200.0, 0.001);
});

it('scopes dentist to own todays appointments and pending treatments', function () {
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'date' => '2026-05-15',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'date' => '2026-05-15',
    ]);

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Pending',
        'price' => 10,
        'date' => '2026-05-05',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'payment_status' => 'Pending',
        'price' => 20,
        'date' => '2026-05-05',
    ]);

    $response = $this->actingAs($this->dentistA, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/stats'));

    $response->assertOk()
        ->assertJsonPath('data.todays_appointments', 1)
        ->assertJsonPath('data.pending_treatments', 1);
});

it('gives nurse calendar wide today appointments but own pending totals', function () {
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'date' => '2026-05-15',
    ]);
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'date' => '2026-05-15',
    ]);

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->nurse->id,
        'payment_status' => 'Pending',
        'price' => 15,
        'date' => '2026-05-05',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'payment_status' => 'Pending',
        'price' => 25,
        'date' => '2026-05-05',
    ]);

    $response = $this->actingAs($this->nurse, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/stats'));

    $response->assertOk()
        ->assertJsonPath('data.todays_appointments', 2)
        ->assertJsonPath('data.pending_treatments', 1);
});

it('excludes pending paid revenue from monthly revenue', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Pending',
        'price' => 500,
        'date' => '2026-05-01',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Paid',
        'price' => 125.5,
        'date' => '2026-05-02',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/stats'))
        ->assertOk()
        ->assertJsonPath('data.monthly_revenue', 125.5);
});

it('does not count zero price treatments as pending', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistA->id,
        'payment_status' => 'Pending',
        'price' => 0,
        'date' => '2026-05-03',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentistB->id,
        'payment_status' => 'Pending',
        'price' => 40,
        'date' => '2026-05-03',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/stats'))
        ->assertOk()
        ->assertJsonPath('data.pending_treatments', 1);
});
