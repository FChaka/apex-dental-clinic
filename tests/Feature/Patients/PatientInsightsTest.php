<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPaymentRecord;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentType;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->staffMember = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
    $this->patient = Patient::factory()->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns computed insights', function () {
    $type = TreatmentType::factory()->create(['name' => 'Cleaning']);
    PatientTreatmentEntry::query()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $type->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-01-10',
        'price' => 200,
        'amount_paid' => 0,
        'payment_status' => 'Paid',
    ]);
    PatientTreatmentEntry::query()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $type->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-02-10',
        'price' => 100,
        'amount_paid' => 0,
        'payment_status' => 'Paid',
    ]);
    PatientPaymentRecord::query()->create([
        'patient_id' => $this->patient->id,
        'date' => '2026-02-11',
        'amount' => 80,
        'method' => 'cash',
        'source' => 'manual',
    ]);
    Appointment::query()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-03-01',
        'time' => '10:00:00',
        'treatment' => 'Checkup',
        'status' => 'Completed',
    ]);
    Appointment::query()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-04-01',
        'time' => '11:00:00',
        'treatment' => 'Checkup',
        'status' => 'Completed',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/insights"))
        ->assertOk()
        ->assertJsonPath('data.total_billed', 300)
        ->assertJsonPath('data.total_paid', 80)
        ->assertJsonPath('data.outstanding_balance', 220)
        ->assertJsonPath('data.total_visits', 2)
        ->assertJsonPath('data.most_frequent_treatment', 'Cleaning')
        ->assertJsonPath('data.last_visit', '2026-04-01');
});

it('allows staff to access patient insights', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/insights"))
        ->assertOk();
});
