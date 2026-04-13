<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientAnamnesis;
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

it('returns anamnesis for patient', function () {
    PatientAnamnesis::query()->create([
        'patient_id' => $this->patient->id,
        'chief_complaint' => 'Pain',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/anamnesis"))
        ->assertOk()
        ->assertJsonPath('data.chief_complaint', 'Pain');
});

it('updates anamnesis', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/anamnesis"), [
            'dental_history' => 'Braces as teen',
        ])
        ->assertOk()
        ->assertJsonPath('data.dental_history', 'Braces as teen');
});

it('returns 403 for staff on another patients anamnesis', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/anamnesis"), [
            'other' => 'x',
        ])
        ->assertForbidden();
});
