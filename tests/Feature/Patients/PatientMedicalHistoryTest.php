<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientMedicalHistory;
use App\Models\Tenant\StaffMember;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->staffMember = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
    $this->patient = Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns medical history for patient', function () {
    PatientMedicalHistory::query()->create([
        'patient_id' => $this->patient->id,
        'allergies' => ['latex'],
        'conditions' => [],
        'notes' => 'Note',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/medical-history"))
        ->assertOk()
        ->assertJsonPath('data.notes', 'Note')
        ->assertJsonPath('data.allergies', ['latex']);
});

it('returns empty medical history shape when missing', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/medical-history"))
        ->assertOk()
        ->assertJsonPath('data.allergies', [])
        ->assertJsonPath('data.conditions', []);
});

it('updates medical history', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/medical-history"), [
            'allergies' => ['aspirin'],
            'notes' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Updated');
});

it('returns 403 for staff on another patients medical history', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/medical-history"))
        ->assertForbidden();
});

it('returns 404 for medical history when patient id missing in tenant', function () {
    $missingId = (int) (Patient::query()->max('id') ?? 0) + 50_000;

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$missingId}/medical-history"))
        ->assertNotFound();
});
