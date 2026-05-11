<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\WidgetPreference;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->staff = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns empty arrays when no preferences exist', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/preferences/widgets'))
        ->assertOk()
        ->assertJsonPath('data.dashboard', [])
        ->assertJsonPath('data.reports', []);
});

it('creates preferences if none exist and updates if they do', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/preferences/widgets'), [
            'dashboard' => ['revenue', 'appointments'],
        ])
        ->assertOk()
        ->assertJsonPath('data.dashboard.0', 'revenue');

    expect(WidgetPreference::query()
        ->where('staff_id', $this->staff->id)
        ->where('page', 'dashboard')
        ->exists())->toBeTrue();

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/preferences/widgets'), [
            'dashboard' => ['patients', 'revenue'],
        ])
        ->assertOk();

    expect(WidgetPreference::query()
        ->where('staff_id', $this->staff->id)
        ->where('page', 'dashboard')
        ->count())->toBe(1);
});

it('updates only pages included in the request', function () {
    WidgetPreference::query()->create([
        'staff_id' => $this->staff->id,
        'page' => 'reports',
        'widget_order' => ['trends'],
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/preferences/widgets'), [
            'dashboard' => ['revenue'],
        ])
        ->assertOk()
        ->assertJsonPath('data.dashboard.0', 'revenue')
        ->assertJsonPath('data.reports.0', 'trends');
});
