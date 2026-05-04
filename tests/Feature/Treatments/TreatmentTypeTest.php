<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
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
    $this->patient = Patient::factory()->create();
    $this->treatmentType = TreatmentType::factory()->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lists treatment types ordered by name', function () {
    TreatmentType::factory()->create(['name' => 'Zebra Clean']);
    TreatmentType::factory()->create(['name' => 'Alpha Fill']);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-types'));

    $response->assertOk()
        ->assertJsonPath('message', 'OK');

    $names = collect($response->json('data'))->pluck('name')->all();
    $alphaPos = array_search('Alpha Fill', $names, true);
    $zebraPos = array_search('Zebra Clean', $names, true);
    expect($alphaPos)->not->toBeFalse();
    expect($zebraPos)->not->toBeFalse();
    expect($alphaPos)->toBeLessThan($zebraPos);
});

it('filters treatment types by is_active', function () {
    TreatmentType::factory()->create(['name' => 'Active One', 'is_active' => true]);
    TreatmentType::factory()->create(['name' => 'Inactive One', 'is_active' => false]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-types?is_active=1'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Active One')->not->toContain('Inactive One');
});

it('allows staff to list treatment types', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-types'))
        ->assertOk();
});

it('creates a treatment type as admin', function () {
    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/treatment-types'), [
            'name' => 'New Crown',
            'description' => 'Test',
            'default_duration' => 60,
            'default_price' => 150.50,
            'vat' => 18,
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'New Crown')
        ->assertJsonPath('data.default_price', '150.50');

    expect(TreatmentType::query()->where('name', 'New Crown')->exists())->toBeTrue();
});

it('forbids staff from creating treatment types', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/treatment-types'), [
            'name' => 'X',
            'default_duration' => 30,
            'default_price' => 10,
        ])
        ->assertForbidden();
});

it('updates a treatment type as admin', function () {
    $type = TreatmentType::factory()->create(['name' => 'Old']);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/treatment-types/{$type->id}"), [
            'name' => 'Renamed',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

it('forbids staff from updating treatment types', function () {
    $type = TreatmentType::factory()->create();

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/treatment-types/{$type->id}"), [
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

it('deletes a treatment type when unused', function () {
    $type = TreatmentType::factory()->create();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/treatment-types/{$type->id}"))
        ->assertNoContent();

    expect(TreatmentType::query()->find($type->id))->toBeNull();
});

it('returns 422 when deleting a treatment type in use', function () {
    $type = TreatmentType::factory()->create();
    PatientTreatmentEntry::query()->create([
        'patient_id' => $this->patient->id,
        'treatment_type_id' => $type->id,
        'dentist_id' => $this->admin->id,
        'date' => '2026-05-01',
        'price' => 50.00,
        'amount_paid' => 0,
        'payment_status' => 'Pending',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/treatment-types/{$type->id}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete a treatment type that has been used in patient treatments.');
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-types'))
        ->assertUnauthorized();
});

it('returns 422 on validation failure when creating', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/treatment-types'), [
            'name' => '',
            'default_duration' => 0,
            'default_price' => -1,
        ])
        ->assertUnprocessable();
});

it('returns 404 when updating a non existent treatment type', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/treatment-types/999999'), ['name' => 'Nope'])
        ->assertNotFound();
});

it('does not expose other clinic treatment types in the index', function () {
    $uniqueName = 'CrossTenantType'.uniqid('', true);
    $otherClinic = createTestTenant('other-clinic');
    tenancy()->initialize($otherClinic);
    TreatmentType::factory()->create(['name' => $uniqueName]);
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/treatment-types'));

    $response->assertOk();
    expect($response->getContent())->not->toContain($uniqueName);
});

it('allows super_admin to create treatment types', function () {
    $super = StaffMember::factory()->create(['clinic_access_level' => 'super_admin']);

    $this->actingAs($super, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/treatment-types'), [
            'name' => 'Super Type',
            'default_duration' => 15,
            'default_price' => 25,
        ])
        ->assertCreated();
});
