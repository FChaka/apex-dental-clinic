<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientDocument;
use App\Models\Tenant\StaffMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $this->staffMember = StaffMember::factory()->create(['clinic_access_level' => 'staff']);
    $this->patient = Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lists patient documents', function () {
    PatientDocument::query()->create([
        'patient_id' => $this->patient->id,
        'name' => 'Doc',
        'file_name' => 'a.pdf',
        'type' => 'application/pdf',
        'file_path' => 'tenants/test-clinic/patients/'.$this->patient->id.'/documents/x.pdf',
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
    $path = "tenants/test-clinic/patients/{$this->patient->id}/documents/keep.pdf";
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

it('returns 403 for staff on another patients documents', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}/documents"))
        ->assertForbidden();
});
