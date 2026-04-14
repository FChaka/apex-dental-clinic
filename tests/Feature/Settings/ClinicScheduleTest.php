<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\ClinicSchedule;
use App\Models\Tenant\StaffMember;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->staff = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns schedule rows ordered by day_of_week', function () {
    ClinicSchedule::query()->create([
        'day_of_week' => 2,
        'is_open' => true,
        'start_hour' => '08:00:00',
        'end_hour' => '17:00:00',
    ]);
    ClinicSchedule::query()->create([
        'day_of_week' => 1,
        'is_open' => false,
        'start_hour' => '08:00:00',
        'end_hour' => '17:00:00',
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/settings/schedule'))
        ->assertOk()
        ->assertJsonPath('data.0.day_of_week', 1)
        ->assertJsonPath('data.1.day_of_week', 2);
});

it('updates all 7 schedule rows atomically', function () {
    $schedule = collect(range(0, 6))->map(fn (int $day) => [
        'day_of_week' => $day,
        'is_open' => $day >= 1 && $day <= 5,
        'start_hour' => 8,
        'end_hour' => 17,
    ])->all();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/settings/schedule'), ['schedule' => $schedule])
        ->assertOk();

    expect(ClinicSchedule::query()->count())->toBe(7);
    expect(ClinicSchedule::query()->where('day_of_week', 0)->first()?->is_open)->toBeFalse();
});

it('forbids staff from updating schedule', function () {
    $schedule = collect(range(0, 6))->map(fn (int $day) => [
        'day_of_week' => $day,
        'is_open' => false,
        'start_hour' => 8,
        'end_hour' => 17,
    ])->all();

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/settings/schedule'), ['schedule' => $schedule])
        ->assertForbidden();
});

it('validates schedule size and hour ordering', function () {
    $bad = [
        ['day_of_week' => 0, 'is_open' => true, 'start_hour' => 8, 'end_hour' => 8],
    ];

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/settings/schedule'), ['schedule' => $bad])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule']);
});
