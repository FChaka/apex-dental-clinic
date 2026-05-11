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
    $this->clinic = createTestTenant('daily-rpt-'.uniqid());
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->dentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);
    $this->patient = Patient::factory()->create([
        'name' => 'Jane',
        'surname' => 'Doe',
    ]);

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'Zero',
        'date' => '2026-01-15',
        'duration_minutes' => 30,
        'payment_status' => 'Pending',
        'price' => 0,
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'PaidOne',
        'date' => '2026-01-15',
        'duration_minutes' => 30,
        'payment_status' => 'Paid',
        'price' => 100,
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'name' => 'PendingOne',
        'date' => '2026-01-15',
        'duration_minutes' => 30,
        'payment_status' => 'Pending',
        'price' => 150,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('applies zero price effective paid rule for totals and rows', function () {
    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/daily?date=2026-01-15'));

    $response->assertOk();

    expect($response->json('data.totals.total'))->toEqualWithDelta(250.0, 0.001)
        ->and($response->json('data.totals.paid'))->toEqualWithDelta(100.0, 0.001)
        ->and($response->json('data.totals.pending'))->toEqualWithDelta(150.0, 0.001);

    $rows = collect($response->json('data.rows'));
    $zeroRow = $rows->firstWhere('treatment_type', 'Zero');
    expect($zeroRow)->not->toBeNull()
        ->and($zeroRow['effective_payment_status'])->toBe('paid');
});
