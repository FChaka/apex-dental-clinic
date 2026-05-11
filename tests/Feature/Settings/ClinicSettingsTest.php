<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\ClinicSetting;
use App\Models\Tenant\StaffMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('returns general clinic settings', function () {
    ClinicSetting::query()->updateOrCreate(['id' => 1], [
        'clinic_name' => 'Smile Center',
        'city' => 'Pristina',
    ]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/settings/general'))
        ->assertOk()
        ->assertJsonPath('data.clinic_name', 'Smile Center')
        ->assertJsonMissingPath('data.id');
});

it('updates general clinic settings and uploads logo', function () {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');

    $oldPath = 'tenants/test-clinic/settings/logo.png';
    Storage::disk($disk)->put($oldPath, 'old');

    ClinicSetting::query()->updateOrCreate(['id' => 1], [
        'clinic_name' => 'Old',
        'logo_path' => $oldPath,
    ]);

    $file = UploadedFile::fake()->image('logo.jpg', 120, 120);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(array_merge(
            clinicStatefulHeaders($this->clinic),
            ['Accept' => 'application/json']
        ))
        ->put(clinicApiUrl($this->clinic, 'api/settings/general'), [
            'clinic_name' => 'New Clinic',
            'logo' => $file,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.clinic_name', 'New Clinic')
        ->assertJsonStructure(['data' => ['logo_url']]);

    Storage::disk($disk)->assertMissing($oldPath);

    $logoUrl = $response->json('data.logo_url');
    expect($logoUrl)->toBeString()->toStartWith('data:image/');
});

it('forbids staff from updating general settings', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/settings/general'), [
            'clinic_name' => 'Nope',
        ])
        ->assertForbidden();
});

it('returns invoice settings', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/settings/invoice'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['bank_name', 'iban', 'swift', 'account_holder', 'other_details'],
            'message',
        ]);
});

it('updates invoice settings', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/settings/invoice'), [
            'bank_name' => 'Bank',
            'iban' => 'IBAN',
        ])
        ->assertOk()
        ->assertJsonPath('data.bank_name', 'Bank')
        ->assertJsonPath('data.iban', 'IBAN');
});

it('returns date-time settings', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/settings/date-time'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['time_zone_mode', 'manual_time_zone', 'date_format'],
            'message',
        ]);
});

it('validates date-time settings', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/settings/date-time'), [
            'time_zone_mode' => 'bad',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['time_zone_mode']);
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/settings/general'))
        ->assertUnauthorized();
});
