<?php

declare(strict_types=1);

use App\Models\Central\Clinic;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentType;
use App\Support\TenantPatientStoragePaths;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->clinic = createTestTenant('test-clinic');
    tenancy()->initialize($this->clinic);

    $this->admin = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
    ]);
    $this->staff = StaffMember::factory()->create([
        'clinic_access_level' => 'staff',
    ]);
    $this->patient = Patient::factory()->create();
    $this->treatmentType = TreatmentType::factory()->create();
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('generates sequential invoice numbers per year', function () {
    $first = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 100.00,
        ])
        ->assertCreated()
        ->json('data.invoice_number');

    $second = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 200.00,
        ])
        ->assertCreated()
        ->json('data.invoice_number');

    expect($first)->toBe('INV-2026-0001');
    expect($second)->toBe('INV-2026-0002');
});

it('forbids staff from creating invoices', function () {
    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 50.00,
        ])
        ->assertForbidden();
});

it('forbids staff from updating invoices', function () {
    $invoice = Invoice::factory()->create(['patient_id' => $this->patient->id]);

    $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}"), [
            'status' => 'Paid',
        ])
        ->assertForbidden();
});

it('lists invoices with patient and pagination', function () {
    Invoice::factory()->create([
        'patient_id' => $this->patient->id,
        'invoice_number' => 'INV-2026-0099',
        'date' => '2026-04-01',
        'due_date' => '2026-04-10',
        'amount' => 10.00,
    ]);

    $response = $this->actingAs($this->staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/invoices'));

    $response->assertOk()
        ->assertJsonPath('data.0.invoice_number', 'INV-2026-0099')
        ->assertJsonStructure(['meta' => ['total', 'per_page', 'current_page']]);
});

it('filters invoices by search status and dates', function () {
    Invoice::factory()->create([
        'patient_id' => $this->patient->id,
        'invoice_number' => 'INV-2026-0200',
        'date' => '2026-07-10',
        'due_date' => '2026-07-20',
        'amount' => 99.00,
        'status' => 'Paid',
    ]);

    $url = 'api/invoices?'.http_build_query([
        'search' => '0200',
        'status' => 'Paid',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, $url))
        ->assertOk()
        ->assertJsonPath('data.0.invoice_number', 'INV-2026-0200');
});

it('shows invoice with treatment entries', function () {
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->admin->id,
        'treatment_type_id' => $this->treatmentType->id,
        'price' => 55.00,
        'tooth_number' => '14',
    ]);

    $invoice = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 55.00,
            'treatment_entry_ids' => [$entry->id],
        ])
        ->assertCreated()
        ->json('data');

    $invoiceId = $invoice['id'];

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/invoices/{$invoiceId}"))
        ->assertOk()
        ->assertJsonPath('data.treatment_entries.0.tooth_number', '14')
        ->assertJsonPath('data.treatment_entries.0.treatment_type.name', $this->treatmentType->name);
});

it('rejects treatment entries that belong to another patient', function () {
    $otherPatient = Patient::factory()->create();
    $entry = PatientTreatmentEntry::factory()->create([
        'patient_id' => $otherPatient->id,
        'dentist_id' => $this->admin->id,
        'treatment_type_id' => $this->treatmentType->id,
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 10.00,
            'treatment_entry_ids' => [$entry->id],
        ])
        ->assertUnprocessable();
});

it('updates allowed fields as admin', function () {
    $invoice = Invoice::factory()->create([
        'patient_id' => $this->patient->id,
        'invoice_number' => 'INV-2026-0300',
        'date' => '2026-05-01',
        'due_date' => '2026-05-15',
        'amount' => 100.00,
        'status' => 'Pending',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}"), [
            'status' => 'Paid',
            'amount' => 120.00,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Paid')
        ->assertJsonPath('data.amount', '120.00');
});

it('returns 401 for pdf when unauthenticated', function () {
    $invoice = Invoice::factory()->create(['patient_id' => $this->patient->id]);

    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}/pdf"))
        ->assertUnauthorized();
});

it('generates pdf on disk at store time', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 80.00,
        ])
        ->assertCreated();

    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($invoice->pdf_path);

    $expectedPath = TenantPatientStoragePaths::invoicePdfRelativePath($invoice->load('patient'));
    expect($invoice->pdf_path)->toBe($expectedPath);

    $first = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->get(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}/pdf"));

    $first->assertOk();
    $first->assertHeader('content-type', 'application/pdf');

    $second = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->get(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}/pdf"));

    $second->assertOk();
    expect($first->getContent())->toBe($second->getContent());
    Storage::disk('local')->assertExists($invoice->fresh()->pdf_path);
});

it('regenerates pdf after invoice update', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 80.00,
        ])
        ->assertCreated();

    $invoice = Invoice::query()->firstOrFail();
    $originalPath = $invoice->pdf_path;
    expect($originalPath)->not->toBeNull();
    Storage::disk('local')->assertExists($originalPath);

    $originalContent = Storage::disk('local')->get($originalPath);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}"), [
            'amount' => 90.00,
        ])
        ->assertOk();

    $invoice->refresh();
    expect($invoice->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($invoice->pdf_path);
    $newContent = Storage::disk('local')->get($invoice->pdf_path);
    expect($newContent)->not->toBe($originalContent);
});

it('serves cached pdf after patient name change using stored pdf_path', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');

    $this->patient->update([
        'name' => 'Alice',
        'surname' => 'Smith',
    ]);

    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/invoices'), [
            'patient_id' => $this->patient->id,
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'amount' => 40.00,
        ])
        ->assertCreated();

    $invoice = Invoice::query()->firstOrFail();
    $storedPath = $invoice->pdf_path;
    expect($storedPath)->not->toBeNull();
    Storage::disk('local')->assertExists($storedPath);

    $this->patient->update([
        'name' => 'Zara',
        'surname' => 'Jones',
    ]);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->get(clinicApiUrl($this->clinic, "api/invoices/{$invoice->id}/pdf"));

    $response->assertOk();
    expect($invoice->fresh()->pdf_path)->toBe($storedPath);
    Storage::disk('local')->assertExists($storedPath);
});

it('returns 404 for unknown invoice id', function () {
    $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/invoices/999999'))
        ->assertNotFound();
});

it('does not expose other clinic invoices in the index', function () {
    $uniqueNumber = 'INV-ISO-'.str_replace('.', '', uniqid('', true));
    $otherClinic = createTestTenant('other-inv');
    tenancy()->initialize($otherClinic);
    $adminOther = StaffMember::factory()->create(['clinic_access_level' => 'admin']);
    $patientOther = Patient::factory()->create();
    Invoice::factory()->create([
        'patient_id' => $patientOther->id,
        'invoice_number' => $uniqueNumber,
    ]);
    tenancy()->end();

    tenancy()->initialize($this->clinic);

    $response = $this->actingAs($this->admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/invoices'));

    $response->assertOk();
    expect($response->getContent())->not->toContain($uniqueNumber);
});
