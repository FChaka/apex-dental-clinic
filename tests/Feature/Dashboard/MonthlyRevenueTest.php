<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-05-15 12:00:00', 'UTC'));
    $this->clinic = createTestTenant('monthly-rev-'.uniqid());
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->dentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);
    $this->patient = Patient::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('returns the requested span of calendar months including paid collected rule', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'payment_status' => 'Paid',
        'price' => 100,
        'date' => '2026-03-10',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'payment_status' => 'Pending',
        'price' => 500,
        'date' => '2026-04-05',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'payment_status' => 'Paid',
        'price' => 40,
        'date' => '2026-05-02',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'payment_status' => 'Pending',
        'price' => 0,
        'date' => '2026-05-03',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/monthly-revenue?months=3'));

    $response->assertOk();
    $series = $response->json('data.series');
    expect($series)->toHaveCount(3)
        ->and($series[0]['month'])->toBe('2026-03')
        ->and((float) $series[0]['revenue'])->toBe(100.0)
        ->and((float) $series[1]['revenue'])->toBe(0.0)
        ->and((float) $series[2]['revenue'])->toBe(40.0);
});

it('scopes monthly revenue for acting dentist staff', function () {
    $other = StaffMember::factory()->create(['clinic_access_level' => 'staff']);

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'payment_status' => 'Paid',
        'price' => 80,
        'date' => '2026-05-01',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $other->id,
        'payment_status' => 'Paid',
        'price' => 300,
        'date' => '2026-05-02',
    ]);

    $response = $this->actingAs($this->dentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/dashboard/monthly-revenue?months=1'));

    $response->assertOk();
    $last = collect($response->json('data.series'))->last();
    expect((float) $last['revenue'])->toBe(80.0);
});
