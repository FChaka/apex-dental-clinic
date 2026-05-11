<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientXray;
use App\Models\Tenant\StaffMember;
use App\Support\TenantPatientStoragePaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('lists x-rays for a patient with expected shape', function () {
    Storage::fake(config('filesystems.default'));
    $img = UploadedFile::fake()->image('one.jpg', 20, 20);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"), [
            'files' => [$img],
        ])
        ->assertCreated();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'file_name',
                    'image_url',
                    'thumbnail_url',
                    'mime_type',
                    'file_size',
                    'notes',
                    'taken_at',
                    'uploaded_by',
                    'created_at',
                ],
            ],
        ]);
});

it('uploads a single x-ray, stores file and generates thumbnail', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $img = UploadedFile::fake()->image('pano.png', 50, 50);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"), [
            'files' => [$img],
            'title' => 'Panoramic',
            'notes' => 'Initial',
            'taken_at' => '2026-01-15',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.0.title', 'Panoramic')
        ->assertJsonPath('data.0.notes', 'Initial')
        ->assertJsonPath('data.0.taken_at', '2026-01-15');

    $xray = PatientXray::query()->first();
    expect($xray)->not->toBeNull();
    expect($xray?->file_path)->not->toBe('')->and($xray?->thumbnail_path)->not->toBe('');
    Storage::disk($disk)->assertExists($xray->file_path);
    Storage::disk($disk)->assertExists($xray->thumbnail_path);
});

it('uploads multiple x-rays in one request', function () {
    Storage::fake(config('filesystems.default'));
    $a = UploadedFile::fake()->image('a.jpg', 5, 5);
    $b = UploadedFile::fake()->image('b.jpg', 5, 5);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"), [
            'files' => [$a, $b],
            'title' => 'Batch',
        ]);

    $response->assertCreated();
    expect(PatientXray::query()->count())->toBe(2);
    expect($response->json('data'))->toHaveCount(2);
    foreach (PatientXray::query()->get() as $x) {
        Storage::disk(config('filesystems.default'))->assertExists($x->file_path);
        Storage::disk(config('filesystems.default'))->assertExists($x->thumbnail_path);
    }
});

it('updates x-ray metadata only', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $filePath = "tenants/test-clinic/patients/{$segment}/xrays/u.jpg";
    $thumbPath = "tenants/test-clinic/patients/{$segment}/xrays/thumbs/u.jpg";
    Storage::disk($disk)->put($filePath, 'x');
    Storage::disk($disk)->put($thumbPath, 't');

    $x = PatientXray::query()->create([
        'patient_id' => $this->patient->id,
        'title' => 'Old',
        'file_name' => 'u.jpg',
        'file_path' => $filePath,
        'thumbnail_path' => $thumbPath,
        'mime_type' => 'image/jpeg',
        'file_size' => 1,
        'notes' => 'n1',
        'taken_at' => '2020-01-01',
        'uploaded_by' => $this->admin->id,
    ]);

    $oldPath = $x->file_path;

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays/{$x->id}"), [
            'title' => 'New',
            'notes' => 'n2',
            'taken_at' => '2025-12-01',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New')
        ->assertJsonPath('data.notes', 'n2')
        ->assertJsonPath('data.taken_at', '2025-12-01');

    $x->refresh();
    expect($x->file_path)->toBe($oldPath);
    expect(Storage::disk($disk)->get($x->file_path))->toBe('x');
});

it('deletes x-ray record and files', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $filePath = "tenants/test-clinic/patients/{$segment}/xrays/del.jpg";
    $thumbPath = "tenants/test-clinic/patients/{$segment}/xrays/thumbs/del.jpg";
    Storage::disk($disk)->put($filePath, 'full');
    Storage::disk($disk)->put($thumbPath, 'thumb');

    $x = PatientXray::query()->create([
        'patient_id' => $this->patient->id,
        'title' => null,
        'file_name' => 'del.jpg',
        'file_path' => $filePath,
        'thumbnail_path' => $thumbPath,
        'mime_type' => 'image/jpeg',
        'file_size' => 4,
        'notes' => null,
        'taken_at' => null,
        'uploaded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays/{$x->id}"))
        ->assertOk()
        ->assertJsonPath('message', 'X-ray deleted successfully.');

    expect(PatientXray::query()->whereKey($x->id)->exists())->toBeFalse();
    Storage::disk($disk)->assertMissing($filePath);
    Storage::disk($disk)->assertMissing($thumbPath);
});

it('returns 401 for unauthenticated list', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"))
        ->assertUnauthorized()
        ->assertJsonPath('data', null);
});

it('allows staff to view patient x-rays', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"))
        ->assertOk();
});

it('rejects invalid file type', function () {
    Storage::fake(config('filesystems.default'));
    $bad = UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json'],
        ))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"), [
            'files' => [$bad],
        ])
        ->assertUnprocessable();
});

it('rejects files over 10MB', function () {
    Storage::fake(config('filesystems.default'));
    $huge = UploadedFile::fake()->create('huge.jpg', 10241, 'image/jpeg');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json'],
        ))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"), [
            'files' => [$huge],
        ])
        ->assertUnprocessable();
});

it('does not list x-rays from another tenant', function () {
    Storage::fake(config('filesystems.default'));
    $img = UploadedFile::fake()->image('iso.jpg', 5, 5);
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"), [
            'files' => [$img],
        ])
        ->assertCreated();

    $other = createTestTenant('other-xray-clinic');
    tenancy()->initialize($other);
    $adminB = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $patientB = Patient::factory()->create();
    expect(PatientXray::query()->count())->toBe(0);
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays"))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    dropTenantDatabaseIfExists($other);
});

it('returns the full x-ray image with correct content for GET image', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $filePath = "tenants/test-clinic/patients/{$segment}/xrays/shot.png";
    $thumbPath = "tenants/test-clinic/patients/{$segment}/xrays/thumbs/shot.jpg";
    Storage::disk($disk)->put($filePath, 'not-a-real-png-payload');
    Storage::disk($disk)->put($thumbPath, 't');

    $x = PatientXray::query()->create([
        'patient_id' => $this->patient->id,
        'title' => null,
        'file_name' => 'shot.png',
        'file_path' => $filePath,
        'thumbnail_path' => $thumbPath,
        'mime_type' => 'image/png',
        'file_size' => 30,
        'notes' => null,
        'taken_at' => null,
        'uploaded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->get(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/xrays/{$x->id}/image"))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('returns 404 when x-ray belongs to another patient', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $p = "tenants/test-clinic/patients/{$segment}/xrays/orphan.jpg";
    Storage::disk($disk)->put($p, 'x');
    $x = PatientXray::query()->create([
        'patient_id' => $this->patient->id,
        'file_name' => 'o.jpg',
        'file_path' => $p,
        'thumbnail_path' => "tenants/test-clinic/patients/{$segment}/xrays/thumbs/orphan.jpg",
        'mime_type' => 'image/jpeg',
        'file_size' => 1,
        'uploaded_by' => $this->admin->id,
    ]);

    $otherPatient = Patient::factory()->create();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$otherPatient->id}/xrays/{$x->id}"))
        ->assertNotFound();
});
