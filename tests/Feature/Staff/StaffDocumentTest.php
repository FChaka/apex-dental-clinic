<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\StaffDocument;
use App\Models\Tenant\StaffMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->staff = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
    $this->target = StaffMember::factory()->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('forbids peers from listing documents for Off Duty colleague', function () {
    Storage::fake(config('filesystems.default'));

    $offDuty = StaffMember::factory()->create(['status' => 'Off Duty']);
    StaffDocument::query()->create([
        'staff_id' => $offDuty->id,
        'name' => 'Hidden',
        'type' => 'license',
        'file_name' => 'a.pdf',
        'file_path' => 'x.pdf',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$offDuty->id}/documents"))
        ->assertForbidden();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$offDuty->id}/documents"))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Hidden');
});

it('lists staff documents for all roles', function () {
    StaffDocument::query()->create([
        'staff_id' => $this->target->id,
        'name' => 'License',
        'type' => 'license',
        'file_name' => 'a.pdf',
        'file_path' => "tenants/test-clinic/staff/{$this->target->id}/documents/x.pdf",
        'uploaded_at' => now(),
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/staff/{$this->target->id}/documents"))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'License');
});

it('uploads a staff document (admin only)', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');

    $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->post(clinicApiUrl($this->clinic, "api/staff/{$this->target->id}/documents"), [
            'file' => $file,
            'name' => 'My License',
            'type' => 'license',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'file_path', 'uploaded_at']]);

    Storage::disk($disk)->assertExists($response->json('data.file_path'));
});

it('forbids staff from uploading a staff document', function () {
    $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->post(clinicApiUrl($this->clinic, "api/staff/{$this->target->id}/documents"), [
            'file' => $file,
            'name' => 'Nope',
            'type' => 'license',
        ])
        ->assertForbidden();
});

it('deletes staff document and file (admin only)', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');

    $path = "tenants/test-clinic/staff/{$this->target->id}/documents/keep.pdf";
    Storage::disk($disk)->put($path, 'binary');

    $doc = StaffDocument::query()->create([
        'staff_id' => $this->target->id,
        'name' => 'Del',
        'type' => 'other',
        'file_name' => 'keep.pdf',
        'file_path' => $path,
        'uploaded_at' => now(),
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/staff/{$this->target->id}/documents/{$doc->id}"))
        ->assertNoContent();

    Storage::disk($disk)->assertMissing($path);
    expect(StaffDocument::query()->whereKey($doc->id)->exists())->toBeFalse();
});

it('returns 404 when document does not belong to staff', function () {
    $other = StaffMember::factory()->create();
    $doc = StaffDocument::query()->create([
        'staff_id' => $other->id,
        'name' => 'x',
        'type' => 'other',
        'file_name' => 'x.pdf',
        'file_path' => 'x',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/staff/{$this->target->id}/documents/{$doc->id}"))
        ->assertNotFound();
});
