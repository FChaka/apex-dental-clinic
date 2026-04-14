<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StaffWorkingSchedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->superAdmin = StaffMember::factory()->create([
        'clinic_access_level' => 'super_admin',
        'role' => 'Dentist',
    ]);
    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
        'role' => 'Dentist',
    ]);
    $this->staff = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lists staff for all roles', function () {
    StaffMember::factory()->create(['name' => 'Alice', 'role' => 'Dentist']);
    StaffMember::factory()->create(['name' => 'Bob', 'role' => 'Receptionist']);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/staff'))
        ->assertOk()
        ->assertJsonStructure(['data', 'message']);
});

it('filters staff by role', function () {
    StaffMember::factory()->create(['role' => 'Dentist']);
    StaffMember::factory()->create(['role' => 'Receptionist']);

    $response = $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/staff?role=Receptionist'));

    $response->assertOk();
    expect(collect($response->json('data'))->every(fn (array $row) => $row['role'] === 'Receptionist'))->toBeTrue();
});

it('admin can create staff and default working schedule is created', function () {
    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/staff'), [
            'name' => 'New Staff',
            'email' => 'new@example.com',
            'role' => 'Dentist',
            'username' => 'newstaff',
            'sign_in_method' => 'pin',
            'pin_length' => 4,
            'login_pin' => '1234',
            'clinic_access_level' => 'super_admin', // should be ignored for admin
        ]);

    $response->assertCreated()
        ->assertJsonMissingPath('data.login_pin')
        ->assertJsonMissingPath('data.login_password')
        ->assertJsonPath('data.username', 'newstaff');

    $createdId = (int) $response->json('data.id');
    expect(StaffWorkingSchedule::query()->where('staff_id', $createdId)->count())->toBe(7);

    $created = StaffMember::query()->findOrFail($createdId);
    expect($created->clinic_access_level)->toBe('staff');
});

it('super_admin can set clinic_access_level on create', function () {
    $response = $this->actingAs($this->superAdmin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/staff'), [
            'name' => 'Admin2',
            'email' => 'admin2@example.com',
            'role' => 'Dentist',
            'username' => 'admin2',
            'sign_in_method' => 'password',
            'login_password' => 'secret',
            'clinic_access_level' => 'admin',
        ]);

    $response->assertCreated();

    $created = StaffMember::query()->findOrFail((int) $response->json('data.id'));
    expect($created->clinic_access_level)->toBe('admin');
});

it('staff cannot create staff', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/staff'), [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'role' => 'Dentist',
            'username' => 'nope',
            'sign_in_method' => 'pin',
            'login_pin' => '1234',
        ])
        ->assertForbidden();
});

it('staff can update own limited profile fields only', function () {
    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'specialty' => null,
    ]);

    $this->actingAs($target, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'name' => 'Updated Name',
            'specialty' => 'Ortho',
            'clinic_access_level' => 'admin',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.specialty', 'Ortho');

    $target->refresh();
    expect($target->clinic_access_level)->toBe('staff');
});

it('admin can update avatar and credentials are never returned', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');

    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'avatar_path' => 'tenants/test-clinic/staff/99/avatar.png',
    ]);
    Storage::disk($disk)->put($target->avatar_path, 'old');

    $file = UploadedFile::fake()->image('avatar.png', 120, 120);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->put(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'avatar' => $file,
            'login_pin' => '9999',
        ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.login_pin')
        ->assertJsonMissingPath('data.login_password')
        ->assertJsonStructure(['data' => ['avatar_path']]);

    Storage::disk($disk)->assertMissing('tenants/test-clinic/staff/99/avatar.png');
    Storage::disk($disk)->assertExists($response->json('data.avatar_path'));
});

it('receptionist cannot delete staff even with admin access', function () {
    $receptionist = StaffMember::factory()->create([
        'role' => 'Receptionist',
        'clinic_access_level' => 'admin',
    ]);
    $target = StaffMember::factory()->create();

    $this->actingAs($receptionist, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"))
        ->assertForbidden()
        ->assertJsonPath('message', 'Receptionists cannot delete staff members.');
});

it('cannot delete staff with upcoming appointments', function () {
    $target = StaffMember::factory()->create();

    Appointment::factory()->create([
        'dentist_id' => $target->id,
        'status' => 'Upcoming',
        'date' => now()->addDay()->toDateString(),
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete a staff member with upcoming appointments.');
});

it('soft deletes staff when allowed', function () {
    $target = StaffMember::factory()->create();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"))
        ->assertNoContent();

    expect(StaffMember::withTrashed()->find($target->id)?->deleted_at)->not->toBeNull();
});
