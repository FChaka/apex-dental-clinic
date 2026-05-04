<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\LeaveRequest;
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

it('staff can submit leave request only for themselves', function () {
    $response = $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/leave-requests'), [
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'note' => 'Vacation',
            'staff_id' => 999, // ignored
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.staff_id', $this->staff->id)
        ->assertJsonPath('data.status', 'Pending');
});

it('index is scoped for staff and unscoped for admin', function () {
    LeaveRequest::query()->create([
        'staff_id' => $this->staff->id,
        'start_date' => '2026-04-10',
        'end_date' => '2026-04-10',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    $other = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
    LeaveRequest::query()->create([
        'staff_id' => $other->id,
        'start_date' => '2026-04-11',
        'end_date' => '2026-04-11',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/leave-requests'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/leave-requests'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('admin can approve and sets responded_at', function () {
    $req = LeaveRequest::query()->create([
        'staff_id' => $this->staff->id,
        'start_date' => '2026-04-10',
        'end_date' => '2026-04-10',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/leave-requests/{$req->id}"), [
            'status' => 'Approved',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.responded_at', fn ($v) => is_string($v) && $v !== '');
});

it('staff can edit own pending request but cannot approve', function () {
    $req = LeaveRequest::query()->create([
        'staff_id' => $this->staff->id,
        'start_date' => '2026-04-10',
        'end_date' => '2026-04-10',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/leave-requests/{$req->id}"), [
            'note' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.note', 'Updated');

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/leave-requests/{$req->id}"), [
            'status' => 'Approved',
        ])
        ->assertForbidden();
});

it('staff can delete own request but not others', function () {
    $mine = LeaveRequest::query()->create([
        'staff_id' => $this->staff->id,
        'start_date' => '2026-04-10',
        'end_date' => '2026-04-10',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    $other = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
    $theirs = LeaveRequest::query()->create([
        'staff_id' => $other->id,
        'start_date' => '2026-04-11',
        'end_date' => '2026-04-11',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/leave-requests/{$mine->id}"))
        ->assertNoContent();

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/leave-requests/{$theirs->id}"))
        ->assertForbidden();
});

it('admin can create leave request for another staff member', function () {
    $target = StaffMember::factory()->create(['clinic_access_level' => 'staff']);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/leave-requests'), [
            'staff_id' => $target->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'note' => 'Conference',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.staff_id', $target->id)
        ->assertJsonPath('data.status', 'Pending');

    expect(LeaveRequest::query()->where('staff_id', $target->id)->exists())->toBeTrue();
});

it('receptionist can create leave request for another staff member', function () {
    $receptionist = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);
    $target = StaffMember::factory()->create(['clinic_access_level' => 'staff']);

    $response = $this->actingAs($receptionist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/leave-requests'), [
            'staff_id' => $target->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'note' => 'Sick leave',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.staff_id', $target->id)
        ->assertJsonPath('data.status', 'Pending');
});
