<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPaymentRecord;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentType;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);
    $this->staff = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
    ]);
    $this->patient = Patient::factory()->create([
        'assigned_dentist_id' => $this->admin->id,
    ]);
    $this->treatmentType = TreatmentType::factory()->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lists payments for a patient ordered by date desc', function () {
    PatientPaymentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'date' => '2026-01-01',
        'amount' => 10.00,
    ]);
    PatientPaymentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'date' => '2026-06-01',
        'amount' => 20.00,
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments"));

    $response->assertOk();
    expect($response->json('data.0.date'))->toBe('2026-06-01');
});

it('updates treatment entry amount_paid when payment is recorded', function () {
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->admin->id,
        'treatment_type_id' => $this->treatmentType->id,
        'price' => 100.00,
        'amount_paid' => 0,
        'payment_status' => 'Pending',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments"), [
            'date' => '2026-05-01',
            'amount' => 100.00,
            'method' => 'cash',
            'treatment_id' => $entry->id,
            'source' => 'treatment',
        ])
        ->assertCreated();

    $entry->refresh();
    expect($entry->amount_paid)->toBe('100.00');
    expect($entry->payment_status)->toBe('Paid');
});

it('reverses amount_paid when payment linked to treatment is deleted', function () {
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->admin->id,
        'treatment_type_id' => $this->treatmentType->id,
        'price' => 100.00,
        'amount_paid' => 0,
        'payment_status' => 'Pending',
    ]);

    $created = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments"), [
            'date' => '2026-05-01',
            'amount' => 40.00,
            'method' => 'cash',
            'treatment_id' => $entry->id,
        ])
        ->assertCreated();

    $entry->refresh();
    expect($entry->amount_paid)->toBe('40.00');

    $paymentId = $created->json('data.id');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments/{$paymentId}"))
        ->assertNoContent();

    $entry->refresh();
    expect($entry->amount_paid)->toBe('0.00');
    expect($entry->payment_status)->toBe('Pending');
});

it('rejects treatment_id that belongs to another patient', function () {
    $otherPatient = Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $otherPatient->id,
        'dentist_id' => $this->admin->id,
        'treatment_type_id' => $this->treatmentType->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments"), [
            'date' => '2026-05-01',
            'amount' => 10.00,
            'treatment_id' => $entry->id,
        ])
        ->assertUnprocessable();
});

it('forbids staff without patient access', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments"))
        ->assertForbidden();
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments"))
        ->assertUnauthorized();
});

it('returns 404 when deleting payment for wrong patient', function () {
    $other = Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);
    $payment = PatientPaymentRecord::factory()->create(['patient_id' => $other->id]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/payments/{$payment->id}"))
        ->assertNotFound();
});
