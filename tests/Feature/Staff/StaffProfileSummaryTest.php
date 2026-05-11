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
    $this->clinic = createTestTenant('staff-prof-sum-'.uniqid());
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin', 'role' => 'Dentist']);
    $this->clinicalPeer = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'status' => 'Active',
        'experience' => 'About 11 years practice',
    ]);
    $this->clinicalTarget = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'status' => 'Active',
    ]);
    $this->patient = Patient::factory()->create([
        'name' => 'Pat',
        'surname' => 'One',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('allows admin and Active clinical peers with expected envelope', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'name' => 'Checkup',
        'status' => 'Completed',
        'date' => '2026-05-05',
        'price' => 50,
        'payment_status' => 'Paid',
    ]);

    foreach ([$this->admin, $this->clinicalPeer] as $actor) {
        $this->actingAs($actor, 'clinic_session')
            ->withHeaders(clinicStatefulHeaders($this->clinic))
            ->getJson(clinicApiUrl($this->clinic, "api/staff/{$this->clinicalTarget->id}/profile-summary"))
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'years_experience',
                        'this_week_completed_cases',
                        'monthly_completed_cases',
                        'total_cases',
                        'upcoming_appointments',
                    ],
                    'treatments_by_type',
                    'recent_appointments',
                    'revenue',
                ],
                'message',
            ])
            ->assertJsonPath('data.summary.years_experience', null);
    }
});

it('allows self summary when profile status is On Leave', function () {
    $leave = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dental Hygienist',
        'status' => 'On Leave',
        'experience' => 'Roughly 7',
    ]);

    $this->actingAs($leave, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$leave->id}/profile-summary"))
        ->assertSuccessful()
        ->assertJsonPath('data.summary.years_experience', 7);
});

it('forbids clinical peers from Off Duty colleague profile summary', function () {
    $offDuty = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'status' => 'Off Duty',
    ]);

    $this->actingAs($this->clinicalPeer, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$offDuty->id}/profile-summary"))
        ->assertForbidden();
});

it('shifts treatments_by_type totals between month and year periods', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'name' => 'Scaling',
        'status' => 'Completed',
        'date' => '2026-04-05',
        'price' => 10,
        'payment_status' => 'Paid',
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'name' => 'Scaling',
        'status' => 'Completed',
        'date' => '2026-05-05',
        'price' => 15,
        'payment_status' => 'Paid',
    ]);

    $monthly = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$this->clinicalTarget->id}/profile-summary?treatments_period=month"));

    $monthly->assertSuccessful();
    $monthTypes = collect($monthly->json('data.treatments_by_type'));
    expect((int) $monthTypes->firstWhere('treatment_name', 'Scaling')['count'])->toBe(1);

    $yearly = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$this->clinicalTarget->id}/profile-summary?treatments_period=year"));

    $yearly->assertSuccessful();
    $yearTypes = collect($yearly->json('data.treatments_by_type'));
    expect((int) $yearTypes->firstWhere('treatment_name', 'Scaling')['count'])->toBe(2);
});

it('includes recent appointment dated outside current ISO week', function () {
    Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'date' => '2025-03-10',
        'time' => '09:30',
        'status' => 'Completed',
        'treatment' => 'Old crown',
    ]);

    $resp = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$this->clinicalTarget->id}/profile-summary?appointments_limit=50"));

    $resp->assertSuccessful();
    $dates = collect($resp->json('data.recent_appointments'))->pluck('date')->all();
    expect($dates)->toContain('2025-03-10');
});

it('applies zero price effective-paid revenue semantics like daily reports', function () {
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'name' => 'Zero',
        'date' => '2026-05-01',
        'duration_minutes' => 30,
        'payment_status' => 'Pending',
        'price' => 0,
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'name' => 'PaidOne',
        'date' => '2026-05-01',
        'duration_minutes' => 30,
        'payment_status' => 'Paid',
        'price' => 100,
    ]);
    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->clinicalTarget->id,
        'name' => 'PendingOne',
        'date' => '2026-05-01',
        'duration_minutes' => 30,
        'payment_status' => 'Pending',
        'price' => 150,
    ]);

    $resp = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$this->clinicalTarget->id}/profile-summary?revenue_period=month&treatments_period=month"));

    $resp->assertSuccessful();

    expect($resp->json('data.revenue.total'))->toEqualWithDelta(250.0, 0.001)
        ->and($resp->json('data.revenue.paid'))->toEqualWithDelta(100.0, 0.001)
        ->and($resp->json('data.revenue.pending'))->toEqualWithDelta(150.0, 0.001);
});

it('zeros clinical workload surfaces for non-clinical staff targets', function () {
    $receptionist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);

    TreatmentRecord::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $receptionist->id,
        'date' => '2026-05-01',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$receptionist->id}/profile-summary"))
        ->assertSuccessful()
        ->assertJsonPath('data.summary.this_week_completed_cases', 0)
        ->assertJsonPath('data.summary.monthly_completed_cases', 0)
        ->assertJsonPath('data.summary.total_cases', 0)
        ->assertJsonPath('data.summary.upcoming_appointments', 0)
        ->assertJsonPath('data.treatments_by_type', [])
        ->assertJsonPath('data.recent_appointments', []);

    expect((float) $response->json('data.revenue.total'))
        ->toBe(0.0)
        ->and((float) $response->json('data.revenue.paid'))
        ->toBe(0.0)
        ->and((float) $response->json('data.revenue.pending'))
        ->toBe(0.0);
});
