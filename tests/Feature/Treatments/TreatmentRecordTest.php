<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);
    $this->dentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
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

it('lists treatment records for admin with pagination meta', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-records'));

    $response->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonStructure(['meta' => ['current_page', 'per_page', 'total']]);
});

it('scopes treatment records index to acting dentist for staff', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'MineRecord',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->otherDentist->id,
        'name' => 'OtherRecord',
    ]);

    $response = $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-records'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('MineRecord')->not->toContain('OtherRecord');
});

it('filters by search status and date range', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'UniqueSearchName',
        'status' => 'Completed',
        'date' => '2026-06-15',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'Other',
        'status' => 'In Progress',
        'date' => '2026-01-10',
    ]);

    $url = 'api/treatment-records?'.http_build_query([
        'search' => 'UniqueSearch',
        'status' => 'Completed',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
    ]);

    $response = $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, $url));

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toContain('UniqueSearchName');
});

it('creates a treatment record', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/treatment-records'), [
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'name' => 'Cleaning',
            'date' => '2026-05-01',
            'duration_minutes' => 45,
            'price' => 75.00,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Cleaning');
});

it('allows staff to update own treatment record', function () {
    $record = TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'Old',
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/treatment-records/{$record->id}"), [
            'name' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated');
});

it('forbids staff from updating another dentist treatment record', function () {
    $record = TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->otherDentist->id,
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/treatment-records/{$record->id}"), [
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

it('allows admin to update any treatment record', function () {
    $record = TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/treatment-records/{$record->id}"), [
            'name' => 'ByAdmin',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'ByAdmin');
});

it('allows staff to delete own treatment record', function () {
    $record = TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/treatment-records/{$record->id}"))
        ->assertNoContent();
});

it('forbids staff from deleting another dentist record', function () {
    $record = TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->otherDentist->id,
    ]);

    $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/treatment-records/{$record->id}"))
        ->assertForbidden();
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-records'))
        ->assertUnauthorized();
});

it('returns 422 on invalid store payload', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/treatment-records'), [])
        ->assertUnprocessable();
});

it('returns 404 for non existent treatment record', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/treatment-records/999999'), ['name' => 'X'])
        ->assertNotFound();
});

it('does not expose other clinic treatment records', function () {
    $uniqueName = 'OtherClinicTR'.uniqid('', true);
    $otherClinic = createTestTenant('other-clinic-tr');
    tenancy()->initialize($otherClinic);
    $adminOther = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $patientOther = Patient::factory()->create();
    TreatmentRecord::factory()->create([
        'patient_id' => $patientOther->id,
        'dentist_id' => $adminOther->id,
        'name' => $uniqueName,
    ]);
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-records'));

    $response->assertOk();
    expect($response->getContent())->not->toContain($uniqueName);
});
