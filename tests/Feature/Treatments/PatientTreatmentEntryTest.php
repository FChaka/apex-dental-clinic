<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceTreatmentEntry;
use App\Models\Tenant\Patient;
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

it('lists treatments for a patient with eager loads', function () {
    PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-06-01',
        'price' => 80.00,
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"));

    $response->assertOk();
    $row = $response->json('data.0');
    expect($row)->toHaveKey('treatment_type')
        ->and($row['treatment_type']['name'])->not->toBeEmpty()
        ->and($row)->toHaveKey('dentist');
});

it('orders treatments by date descending', function () {
    PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-01-01',
    ]);
    PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-12-01',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"));

    $dates = collect($response->json('data'))->pluck('date')->all();
    expect($dates[0])->toBe('2026-12-01');
});

it('creates a treatment entry and sets payment_status from amounts', function () {
    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"), [
            'treatment_type_id' => $this->treatmentType->id,
            'dentist_id' => $this->admin->id,
            'date' => '2026-05-01',
            'price' => 100.00,
            'amount_paid' => 100.00,
            'payment_status' => 'Pending',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_status', 'Paid');
});

it('sets payment_status pending when amount_paid is less than price', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"), [
            'treatment_type_id' => $this->treatmentType->id,
            'dentist_id' => $this->admin->id,
            'date' => '2026-05-01',
            'price' => 100.00,
            'amount_paid' => 50.00,
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'Pending');
});

it('updates a treatment entry and recalculates payment_status', function () {
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
        'price' => 100.00,
        'amount_paid' => 0,
        'payment_status' => 'Pending',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments/{$entry->id}"), [
            'amount_paid' => 100.00,
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'Paid');
});

it('deletes a treatment entry when not on an invoice', function () {
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments/{$entry->id}"))
        ->assertNoContent();

    expect(PatientTreatmentEntry::query()->find($entry->id))->toBeNull();
});

it('returns 422 when deleting a treatment entry linked to an invoice', function () {
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
    ]);

    $invoice = Invoice::query()->create([
        'patient_id' => $this->patient->id,
        'invoice_number' => 'INV-2026-0001',
        'date' => '2026-05-01',
        'due_date' => '2026-05-15',
        'amount' => 100.00,
        'status' => 'Pending',
    ]);

    InvoiceTreatmentEntry::query()->create([
        'invoice_id' => $invoice->id,
        'treatment_entry_id' => $entry->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments/{$entry->id}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete a treatment entry that is linked to an invoice.');
});

it('forbids staff without patient access from listing treatments', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"))
        ->assertForbidden();
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"))
        ->assertUnauthorized();
});

it('returns 404 when entry belongs to another patient', function () {
    $otherPatient = Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $otherPatient->id,
        'treatment_type_id' => $this->treatmentType->id,
        'dentist_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments/{$entry->id}"), [
            'price' => 50.00,
        ])
        ->assertNotFound();
});

it('returns 422 on validation failure when creating', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"), [])
        ->assertUnprocessable();
});

it('does not expose other clinic patient treatments', function () {
    $uniquePrice = '9999.88';
    $otherClinic = createTestTenant('other-clinic-pt');
    tenancy()->initialize($otherClinic);
    $adminOther = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $patientOther = Patient::factory()->create(['assigned_dentist_id' => $adminOther->id]);
    $typeOther = TreatmentType::factory()->create();
    PatientTreatmentEntry::factory()->create([
        'patient_id' => $patientOther->id,
        'treatment_type_id' => $typeOther->id,
        'dentist_id' => $adminOther->id,
        'price' => $uniquePrice,
    ]);
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/treatments"));

    $response->assertOk();
    expect($response->getContent())->not->toContain($uniquePrice);
});
