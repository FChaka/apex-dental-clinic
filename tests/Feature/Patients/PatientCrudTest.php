<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentType;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);
    $this->staffMember = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
    ]);
    $this->patient = Patient::factory()->create([
        'assigned_dentist_id' => $this->admin->id,
    ]);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('lists patients for admin', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/patients'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'message'])
        ->assertJsonPath('meta.per_page', 20);
});

it('scopes the patient index to assigned patients for staff', function () {
    $mine = Patient::factory()->create(['assigned_dentist_id' => $this->staffMember->id]);
    Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);

    $response = $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/patients'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($this->patient->id);
});

it('allows staff to view their assigned patient', function () {
    $p = Patient::factory()->create(['assigned_dentist_id' => $this->staffMember->id]);

    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$p->id}"))
        ->assertOk()
        ->assertJsonPath('data.id', $p->id);
});

it('returns 403 when staff views another dentists patient', function () {
    $this->actingAs($this->staffMember, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}"))
        ->assertForbidden();
});

it('returns patient detail for admin', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}"))
        ->assertOk()
        ->assertJsonPath('data.id', $this->patient->id)
        ->assertJsonStructure([
            'data' => [
                'medical_history',
                'anamnesis',
                'assigned_dentist',
            ],
        ]);
});

it('returns 404 for missing patient id', function () {
    $missingId = (int) (Patient::query()->max('id') ?? 0) + 10_000;

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/patients/{$missingId}"))
        ->assertNotFound();
});

it('returns 401 when unauthenticated', function () {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/patients'))
        ->assertUnauthorized();
});

it('creates a patient with medical history and anamnesis', function () {
    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/patients'), [
            'name' => 'Jane',
            'surname' => 'Doe',
            'status' => 'Active',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane')
        ->assertJsonPath('data.medical_history.allergies', [])
        ->assertJsonPath('data.anamnesis.chief_complaint', null);

    expect(Patient::query()->where('name', 'Jane')->exists())->toBeTrue();
});

it('returns 422 when store validation fails', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/patients'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates patient with nested medical history and anamnesis', function () {
    $this->patient->medicalHistory()->create([
        'allergies' => [],
        'conditions' => [],
        'notes' => null,
    ]);
    $this->patient->anamnesis()->create([]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/patients/{$this->patient->id}"), [
            'name' => $this->patient->name,
            'medical_history' => [
                'allergies' => ['penicillin'],
                'conditions' => [],
                'notes' => 'Watch BP',
            ],
            'anamnesis' => [
                'chief_complaint' => 'Toothache',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.medical_history.notes', 'Watch BP')
        ->assertJsonPath('data.anamnesis.chief_complaint', 'Toothache');
});

it('filters patients by has_pending_payments', function () {
    $type = TreatmentType::factory()->create();
    $withPending = Patient::factory()->create(['assigned_dentist_id' => $this->admin->id]);
    PatientTreatmentEntry::query()->create([
        'patient_id' => $withPending->id,
        'treatment_type_id' => $type->id,
        'dentist_id' => $this->admin->id,
        'date' => now()->toDateString(),
        'price' => 100,
        'amount_paid' => 0,
        'payment_status' => 'Pending',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/patients?has_pending_payments=1'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($withPending->id);
});

it('searches patients by name', function () {
    Patient::factory()->create(['name' => 'ZoltanUnique', 'surname' => 'Test']);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/patients?search='.urlencode('ZoltanUnique')));

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toContain('ZoltanUnique');
});

it('does not expose other clinic patient data in the index', function () {
    $uniqueEmail = 'cross-tenant-'.uniqid('', true).'@example.com';
    $otherClinic = createTestTenant('other-clinic');
    tenancy()->initialize($otherClinic);
    Patient::factory()->create(['email' => $uniqueEmail]);
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/patients'));

    $response->assertOk();
    expect($response->getContent())->not->toContain($uniqueEmail);
});
