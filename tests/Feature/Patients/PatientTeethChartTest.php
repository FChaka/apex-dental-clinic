<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TeethChartData;
use App\Models\Tenant\TeethChartSurface;

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

it('returns teeth chart data', function () {
    TeethChartData::query()->create([
        'patient_id' => $this->patient->id,
        'tooth_number' => '11',
        'procedure' => 'Filling',
        'is_initial_exam' => false,
        'notes' => null,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"))
        ->assertOk()
        ->assertJsonPath('data.procedures.current.0.tooth_number', '11')
        ->assertJsonPath('data.procedures.initial_exam', []);
});

it('replaces only the saved layer on put', function () {
    TeethChartData::query()->create([
        'patient_id' => $this->patient->id,
        'tooth_number' => '99',
        'procedure' => null,
        'is_initial_exam' => true,
        'notes' => 'old',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"), [
            'is_initial_exam' => false,
            'procedures' => [
                [
                    'tooth_number' => '12',
                    'procedure' => 'Crown',
                    'notes' => 'new',
                ],
            ],
            'surfaces' => [
                [
                    'tooth_number' => '12',
                    'surface_key' => 'O',
                    'values' => [1, 2],
                ],
            ],
        ])
        ->assertOk();

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', true)
            ->count()
    )->toBe(1);
    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', true)
            ->value('tooth_number')
    )->toBe('99');

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->count()
    )->toBe(1);
    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->value('tooth_number')
    )->toBe('12');

    expect(
        TeethChartSurface::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->count()
    )->toBe(1);
});

it('does not overwrite initial exam when saving current state', function () {
    TeethChartData::query()->create([
        'patient_id' => $this->patient->id,
        'tooth_number' => '11',
        'procedure' => 'Filling',
        'is_initial_exam' => true,
        'notes' => null,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"), [
            'is_initial_exam' => false,
            'procedures' => [
                [
                    'tooth_number' => '21',
                    'procedure' => 'Crown',
                    'notes' => null,
                ],
            ],
            'surfaces' => [],
        ])
        ->assertOk();

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', true)
            ->count()
    )->toBe(1);

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->count()
    )->toBe(1);
});

it('does not overwrite current state when saving initial exam', function () {
    TeethChartData::query()->create([
        'patient_id' => $this->patient->id,
        'tooth_number' => '21',
        'procedure' => 'Crown',
        'is_initial_exam' => false,
        'notes' => null,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"), [
            'is_initial_exam' => true,
            'procedures' => [
                [
                    'tooth_number' => '11',
                    'procedure' => 'Filling',
                    'notes' => null,
                ],
            ],
            'surfaces' => [],
        ])
        ->assertOk();

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->count()
    )->toBe(1);
    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->value('tooth_number')
    )->toBe('21');

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', true)
            ->count()
    )->toBe(1);
    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', true)
            ->value('tooth_number')
    )->toBe('11');
});

it('replaces only the latest current state when saving current twice', function () {
    TeethChartData::query()->create([
        'patient_id' => $this->patient->id,
        'tooth_number' => '11',
        'procedure' => 'Filling',
        'is_initial_exam' => true,
        'notes' => null,
    ]);

    $url = clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart");
    $headers = clinicStatefulHeaders($this->clinic);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders($headers)
        ->putJson($url, [
            'is_initial_exam' => false,
            'procedures' => [
                [
                    'tooth_number' => '21',
                    'procedure' => 'Crown',
                    'notes' => null,
                ],
            ],
            'surfaces' => [],
        ])
        ->assertOk();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders($headers)
        ->putJson($url, [
            'is_initial_exam' => false,
            'procedures' => [
                [
                    'tooth_number' => '22',
                    'procedure' => 'Filling',
                    'notes' => null,
                ],
            ],
            'surfaces' => [],
        ])
        ->assertOk();

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', true)
            ->count()
    )->toBe(1);

    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->count()
    )->toBe(1);
    expect(
        TeethChartData::query()
            ->where('patient_id', $this->patient->id)
            ->where('is_initial_exam', false)
            ->value('tooth_number')
    )->toBe('22');
});

it('returns 422 when top-level is_initial_exam is missing', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"), [
            'procedures' => [],
            'surfaces' => [],
        ])
        ->assertUnprocessable();
});

it('returns both layers separately in show response shape', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'procedures' => [
                    'initial_exam',
                    'current',
                ],
                'surfaces' => [
                    'initial_exam',
                    'current',
                ],
            ],
            'message',
        ]);
});

it('allows staff to access a patients teeth chart', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/teeth-chart"))
        ->assertOk();
});
