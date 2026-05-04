<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StaffWorkingSchedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

it('rejects self sign-in sensitive update without current_secret', function () {
    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);

    $this->actingAs($target, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'login_pin' => '0000',
            'current_secret' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Current credentials are required to change sign-in settings!');
});

it('staff can update own pin when current_secret is valid', function () {
    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);

    $this->actingAs($target, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'login_pin' => '0000',
            'current_secret' => '1234',
        ])
        ->assertOk()
        ->assertJsonMissingPath('data.login_pin');

    $target->refresh();
    expect(Hash::check('0000', (string) $target->getRawOriginal('login_pin')))->toBeTrue();
});

it('admin can update avatar and credentials are never returned', function () {
    $oldDisk = (string) config('filesystems.default');
    Storage::fake($oldDisk);
    Storage::fake('public');

    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'avatar_path' => 'tenants/test-clinic/staff/99/avatar.png',
    ]);
    Storage::disk($oldDisk)->put($target->avatar_path, 'old');

    $file = UploadedFile::fake()->image('avatar.png', 120, 120);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->put(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'avatar' => $file,
            'login_pin' => '9999',
            'current_secret' => '1234',
        ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.login_pin')
        ->assertJsonMissingPath('data.login_password')
        ->assertJsonStructure(['data' => ['avatar_path', 'avatar_url']]);

    Storage::disk($oldDisk)->assertMissing('tenants/test-clinic/staff/99/avatar.png');
    Storage::disk('public')->assertExists($response->json('data.avatar_path'));
    expect($response->json('data.avatar_url'))->toBeString();
    expect((string) $response->json('data.avatar_url'))->toContain('/storage/');
});

it('rejects sign-in sensitive staff update without current_secret', function () {
    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'login_pin' => '9999',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Current credentials are required to change sign-in settings!');
});

it('rejects sign-in sensitive staff update when current_secret is wrong', function () {
    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'login_pin' => '9999',
            'current_secret' => 'wrong-pin',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Current credentials are required to change sign-in settings!');
});

it('rejects username change without current_secret', function () {
    $target = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
        'username' => 'originaluser',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'username' => 'newusername',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Current credentials are required to change sign-in settings!');
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

it('returns avatar_url as /api stream URL when avatar exists only on local disk', function () {
    $defaultDisk = (string) config('filesystems.default');
    Storage::fake($defaultDisk);

    $target = StaffMember::factory()->create();
    $path = "tenants/test-clinic/staff/{$target->id}/avatar.jpg";
    Storage::disk($defaultDisk)->put($path, 'binary-pretend');
    $target->update(['avatar_path' => $path]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/staff'))
        ->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $target->id);
    expect($row)->not->toBeNull()
        ->and((string) $row['avatar_url'])->toContain("/api/staff/{$target->id}/avatar")
        ->and((string) $row['avatar_url'])->toContain('tenant=test-clinic');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->get(clinicApiUrl($this->clinic, "api/staff/{$target->id}/avatar"))
        ->assertOk();
});

it('includes avatar_url in staff list when avatar is set', function () {
    Storage::fake('public');
    $target = StaffMember::factory()->create(['name' => 'ZetaWithAvatar']);
    $path = "tenants/test-clinic/staff/{$target->id}/avatar.png";
    Storage::disk('public')->put($path, 'x');
    $target->update(['avatar_path' => $path]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/staff'))
        ->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $target->id);
    expect($row)->not->toBeNull()
        ->and($row['avatar_url'])->toContain('/storage/'.$path)
        ->and($row['avatar_path'])->toBe($path);
});
