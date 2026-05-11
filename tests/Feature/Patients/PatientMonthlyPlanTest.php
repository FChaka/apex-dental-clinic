<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientMonthlyPlan;
use App\Models\Tenant\StaffMember;

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

it('lists monthly plans', function () {
    PatientMonthlyPlan::query()->create([
        'patient_id' => $this->patient->id,
        'plan_name' => 'Plan A',
        'total_amount' => 500,
        'months' => 10,
        'interest_percent' => 0,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/monthly-plans"))
        ->assertOk()
        ->assertJsonPath('data.0.plan_name', 'Plan A');
});

it('creates a monthly plan', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/monthly-plans"), [
            'total_amount' => 1200,
            'months' => 12,
            'plan_name' => 'New',
        ])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', 1200);
});

it('updates and deletes a plan scoped to patient', function () {
    $plan = PatientMonthlyPlan::query()->create([
        'patient_id' => $this->patient->id,
        'total_amount' => 100,
        'months' => 2,
        'interest_percent' => 0,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/monthly-plans/{$plan->id}"), [
            'total_amount' => 200,
            'months' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 200);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/monthly-plans/{$plan->id}"))
        ->assertNoContent();
});

it('returns 404 when plan belongs to another patient', function () {
    $other = Patient::factory()->create();
    $plan = PatientMonthlyPlan::query()->create([
        'patient_id' => $other->id,
        'total_amount' => 50,
        'months' => 1,
        'interest_percent' => 0,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/monthly-plans/{$plan->id}"), [
            'total_amount' => 99,
            'months' => 1,
        ])
        ->assertNotFound();
});

it('allows staff to access patient monthly plans', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/monthly-plans"))
        ->assertOk();
});
