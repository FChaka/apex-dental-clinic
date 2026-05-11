<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-05-15 12:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('matches seeded trend data for three month window and enforces dentist drill down rules', function () {
    $this->clinic = createTestTenant('rpt-overview-'.uniqid());
    tenancy()->initialize($this->clinic);

    $dentist = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'role' => 'Dentist']);
    $otherDentist = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'role' => 'Dentist']);
    $receptionistAssignee = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);
    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $actingDentist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'username' => 'acting_d'.uniqid(),
    ]);

    $patient = Patient::factory()->create([
        'created_at' => CarbonImmutable::parse('2025-06-01 10:00:00', 'UTC'),
    ]);

    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $dentist->id, 'date' => '2026-03-05']);
    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $dentist->id, 'date' => '2026-03-20']);
    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $dentist->id, 'date' => '2026-04-10']);
    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $dentist->id, 'date' => '2026-05-02']);
    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $otherDentist->id, 'date' => '2026-05-03']);
    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $dentist->id, 'date' => '2026-05-04']);
    Appointment::factory()->create(['patient_id' => $patient->id, 'dentist_id' => $receptionistAssignee->id, 'date' => '2026-05-05']);

    Patient::withoutEvents(function (): void {
        Patient::factory()->create(['created_at' => CarbonImmutable::parse('2026-03-12 14:00:00', 'UTC')]);
        Patient::factory()->create(['created_at' => CarbonImmutable::parse('2026-03-25 09:00:00', 'UTC')]);
        Patient::factory()->create(['created_at' => CarbonImmutable::parse('2026-04-18 11:00:00', 'UTC')]);
    });

    TreatmentRecord::factory()->create([
        'patient_id' => $patient->id,
        'dentist_id' => $dentist->id,
        'payment_status' => 'Paid',
        'price' => 300,
        'date' => '2026-03-07',
        'duration_minutes' => 45,
        'name' => 'MixA',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $patient->id,
        'dentist_id' => $otherDentist->id,
        'payment_status' => 'Paid',
        'price' => 450,
        'date' => '2026-04-03',
        'duration_minutes' => 45,
        'name' => 'MixB',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $patient->id,
        'dentist_id' => $dentist->id,
        'payment_status' => 'Pending',
        'price' => 800,
        'date' => '2026-05-05',
        'duration_minutes' => 45,
        'name' => 'Excluded',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $patient->id,
        'dentist_id' => $dentist->id,
        'payment_status' => 'Paid',
        'price' => 50,
        'date' => '2026-05-06',
        'duration_minutes' => 45,
        'name' => 'Cheap',
    ]);

    $response = $this->actingAs($admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/overview?period=3m'));

    $response->assertOk();
    expect($response->json('data.appointment_trend'))->toHaveCount(3)
        ->and($response->json('data.appointment_trend.0.count'))->toBe(2)
        ->and($response->json('data.appointment_trend.1.count'))->toBe(1)
        ->and($response->json('data.appointment_trend.2.count'))->toBe(3)
        ->and($response->json('data.kpis.total_appointments'))->toBe(6)
        ->and($response->json('data.kpis.new_patients'))->toBe(3)
        ->and((float) $response->json('data.kpis.total_revenue'))->toEqualWithDelta(800.0, 0.001);

    $totalRev = (float) $response->json('data.kpis.total_revenue');
    $ta = (int) $response->json('data.kpis.total_appointments');
    expect((float) $response->json('data.kpis.avg_revenue_per_appointment'))->toEqualWithDelta(round($totalRev / $ta, 2), 0.001);

    $this->actingAs($actingDentist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/overview?period=3m&dentist_id='.$otherDentist->id))
        ->assertForbidden();
});

it('merges procedures mix into Other when nine distinct names', function () {
    $this->clinic = createTestTenant('rpt-mix-9-'.uniqid());
    tenancy()->initialize($this->clinic);

    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $dentist = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'role' => 'Dentist']);
    $patient = Patient::factory()->create();

    foreach (range(1, 9) as $idx) {
        TreatmentRecord::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'name' => 'T'.$idx,
            'payment_status' => 'Paid',
            'price' => 100 - ($idx - 1) * 5,
            'date' => '2026-05-10',
            'duration_minutes' => 40,
        ]);
    }

    $response = $this->actingAs($admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/overview?period=3m'));

    $response->assertOk();
    $mix = $response->json('data.procedures_mix');
    expect($mix)->toHaveCount(9);

    $other = collect($mix)->last();
    expect($other['treatment_type'])->toBe('Other')
        ->and($other['count'])->toBe(1)
        ->and((float) $other['revenue'])->toBe(60.0);
});

it('does not emit other when eight or fewer procedure names exist', function () {
    $this->clinic = createTestTenant('rpt-mix-8-'.uniqid());
    tenancy()->initialize($this->clinic);

    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $dentist = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'role' => 'Dentist']);
    $patient = Patient::factory()->create();

    foreach (range(1, 8) as $idx) {
        TreatmentRecord::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'name' => 'S'.$idx,
            'payment_status' => 'Paid',
            'price' => 50,
            'date' => '2026-05-10',
            'duration_minutes' => 40,
        ]);
    }

    $response = $this->actingAs($admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/reports/overview?period=3m'));

    $response->assertOk();
    expect($response->json('data.procedures_mix'))->toHaveCount(8);
    foreach ($response->json('data.procedures_mix') as $row) {
        expect($row['treatment_type'])->not->toBe('Other');
    }
});
