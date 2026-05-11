<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientDocument;
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

it('lists patient documents', function () {
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Doc',
        'file_name' => 'a.pdf',
        'type' => 'application/pdf',
        'file_path' => "tenants/test-clinic/patients/{$segment}/documents/x.pdf",
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents"))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Doc');
});

it('uploads a patient document', function () {
    Storage::fake(config('filesystems.default'));

    $file = UploadedFile::fake()->create('xray.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->post(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents"), [
            'file' => $file,
            'name' => 'X-Ray 2026',
            'type' => 'application/pdf',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'file_path']]);

    $path = $response->json('data.file_path');
    Storage::disk(config('filesystems.default'))->assertExists($path);
});

it('deletes document and file', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $path = "tenants/test-clinic/patients/{$segment}/documents/keep.pdf";
    Storage::disk($disk)->put($path, 'binary');

    $doc = PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Del',
        'file_name' => 'keep.pdf',
        'type' => 'application/pdf',
        'file_path' => $path,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->deleteJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents/{$doc->id}"))
        ->assertNoContent();

    Storage::disk($disk)->assertMissing($path);
    expect(PatientDocument::query()->whereKey($doc->id)->exists())->toBeFalse();
});

it('allows staff to access patient documents', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents"))
        ->assertOk();
});

it('downloads patient document when file exists', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $path = "tenants/test-clinic/patients/{$segment}/documents/keep.pdf";
    Storage::disk($disk)->put($path, 'file-payload');

    $doc = PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Keep',
        'file_name' => 'keep.pdf',
        'type' => 'application/pdf',
        'file_path' => $path,
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->get(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents/{$doc->id}/download"));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('keep.pdf');
    expect($response->streamedContent())->toBe('file-payload');
});

it('allows staff to download patient documents', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $path = "tenants/test-clinic/patients/{$segment}/documents/keep.pdf";
    Storage::disk($disk)->put($path, 'x');

    $doc = PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Keep',
        'file_name' => 'keep.pdf',
        'type' => 'application/pdf',
        'file_path' => $path,
    ]);

    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents/{$doc->id}/download"))
        ->assertOk();
});

it('returns 404 when document belongs to another patient', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $path = "tenants/test-clinic/patients/{$segment}/documents/keep.pdf";
    Storage::disk($disk)->put($path, 'x');

    $doc = PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Keep',
        'file_name' => 'keep.pdf',
        'type' => 'application/pdf',
        'file_path' => $path,
    ]);

    $otherPatient = Patient::factory()->create();

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$otherPatient->id}/documents/{$doc->id}/download"))
        ->assertNotFound();
});

it('returns 404 when document file is missing on disk', function () {
    Storage::fake(config('filesystems.default'));
    $segment = TenantPatientStoragePaths::patientDirectorySegment($this->patient);
    $path = "tenants/test-clinic/patients/{$segment}/documents/missing.pdf";

    $doc = PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Missing',
        'file_name' => 'missing.pdf',
        'type' => 'application/pdf',
        'file_path' => $path,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents/{$doc->id}/download"))
        ->assertNotFound();
});
