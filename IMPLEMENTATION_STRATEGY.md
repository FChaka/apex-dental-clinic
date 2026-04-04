# APEX — Implementation Strategy & Dev Guidelines

> This document is a companion to `PROJECT_BLUEPRINT.md`.  
> It captures the agreed implementation strategy, build order, and rules to follow during development.  
> Attach both files to every Cursor session.

---

## 1. Context

The frontend is already built — vibe-coded in React + Vite, with all data persisted to `localStorage` and some state in-memory (appointments and treatment types are lost on refresh). The goal now is to build a proper Laravel backend and replace all localStorage with real API calls.

**Stack:**
- Frontend: React 18 + Vite + Tailwind + react-router 7
- Backend: Laravel (PHP)
- Multi-tenancy: `stancl/tenancy` v3
- Auth: Laravel Sanctum (two separate guards)
- DB: MySQL 8.x via XAMPP locally, MySQL on server — one central DB (`apex_central`) + one DB per clinic (`apex_clinic_{slug}`)

---

## 2. Core Architecture Rules

These never change. Every decision must respect them.

1. **Multi-tenant, multi-database.** Every clinic gets its own isolated database. No `clinic_id` columns in tenant tables — the database itself is the isolation boundary.
2. **Two separate auth guards.** `platform` guard for platform admins (central DB). `clinic` guard for clinic staff (tenant DB). Both use Sanctum tokens but are completely separate.
3. **Two migration folders.** `database/migrations/central/` and `database/migrations/tenant/`. Never mix them. stancl/tenancy must know which folder is which.
4. **Two model namespaces.** `app/Models/Central/` and `app/Models/Tenant/`. Central models use the `central` connection. Tenant models use the default (switched) connection.
5. **Never build endpoints without a working DB behind them.** All migrations come before feature endpoints.

---

## 3. What NOT to Build in v1

Defer these entirely. They add infrastructure complexity with no value for early clinic customers:

- Smile Design AI feature (`/smile-preview`, Express proxy, face-api.js)
- Social Media canvas builder (`/social-media`)
- Real-time notifications (Laravel Echo + Pusher/Reverb)
- Stripe / payment processor integration (handle billing manually first)
- Platform Admin section (`/admin/*`) — you're the only user, it can be rough for a long time

---

## 4. Build Approach: Vertical Slices

**Never build all endpoints before touching the frontend.** That is waterfall and will cause months of wasted work.

The rule: **build one complete feature at a time** — migration → model → controller → endpoints → wire frontend. Confirm it works end-to-end, then move to the next slice.

This means at any point you have a working, demonstrable product.

---

## 5. Build Order

### Slice 1 — Foundation (do this once, do it right)

This is scaffolding. Everything else depends on it. Do not skip steps or reorder.

```
Step 1: Install & configure stancl/tenancy v3
Step 2: Central DB migrations
          - platform_admins
          - clinics
          - subscriptions
          - subscription_invoices
          - platform_services
          - platform_cost_categories
          - platform_spendings
          - audit_log
Step 3: Tenant DB migrations (ALL tenant tables upfront)
          - clinic_settings
          - clinic_schedules
          - invoice_settings
          - date_time_settings
          - staff_members
          - staff_working_schedules
          - staff_percentage_per_treatment
          - staff_documents
          - leave_requests
          - patients
          - patient_medical_history
          - patient_anamnesis
          - patient_documents
          - patient_monthly_plans
          - teeth_chart_data
          - teeth_chart_surfaces
          - treatment_types
          - patient_treatment_entries
          - patient_payment_records
          - appointments
          - treatment_records
          - invoices
          - invoice_items
          - planner_categories
          - planner_materials
          - notifications
          - user_widget_preferences
          - social_media_content
Step 4: Configure both Sanctum auth guards (platform + clinic)
Step 5: Implement the two login/logout endpoints
Step 6: Wire Login page (/login) and Platform Login (/platform/login) to real auth
```

Why all tenant migrations upfront: when you reach Patients in Slice 3, the `patients` table already exists, models are ready, and you're only writing business logic — no context switching back to DB design mid-feature.

---

### Slice 2 — Clinic Staff Auth

Endpoints:
- `POST /api/auth/login` — username + PIN or password, returns Sanctum token
- `POST /api/auth/logout`
- `GET /api/auth/me` — returns staff member + role + permissions
- `POST /api/auth/switch-staff` — StaffPinModal re-auth within same tenant

Frontend wiring: `Login.tsx`, `clinicSession.ts`, `platformSession.ts`, auth guards in `MainLayout.tsx`

Done when: you can log in with a real staff account, token is stored, protected routes work, and refreshing the page doesn't lose session.

---

### Slice 3 — Patients

The heart of the product. Endpoints:
- Full patient CRUD (`/api/patients`, `/api/patients/{id}`)
- Medical history (`/api/patients/{id}/medical-history`)
- Anamnesis (`/api/patients/{id}/anamnesis`)
- Teeth chart (`/api/patients/{id}/teeth-chart`)
- Documents (file upload — replace base64/localStorage)
- Monthly plans (`/api/patients/{id}/monthly-plans`)

Frontend wiring: `Patients.tsx`, `PatientProfile.tsx`, `PatientTreatments.tsx`, `PatientPayments.tsx`, `TeethChart.tsx`

---

### Slice 4 — Appointments

Critical: appointments are currently **in-memory only** and are lost on every page refresh. This is the most visible bug in the prototype.

Endpoints:
- `GET /api/appointments` (with filters: date range, staff, status)
- `POST /api/appointments`
- `PUT /api/appointments/{id}`
- `DELETE /api/appointments/{id}`

Frontend wiring: `Appointments.tsx`

---

### Slice 5 — Treatments + Billing

These are tightly coupled in the frontend — do them in the same slice.

Endpoints:
- Patient treatment entries (`/api/patients/{id}/treatments`)
- Clinic-level treatment records (`/api/treatments`)
- Treatment types catalog (`/api/settings/treatment-types`) — also currently in-memory only
- Invoices (`/api/invoices`, `/api/invoices/{id}`)
- Patient payment records (`/api/patients/{id}/payments`)

Frontend wiring: `Treatments.tsx`, `Billing.tsx`, `PatientTreatments.tsx`, `PatientPayments.tsx`

---

### Slice 6 — Settings + Staff

Endpoints:
- Clinic settings (`/api/settings/general`, `/api/settings/schedule`, `/api/settings/invoice`, `/api/settings/datetime`)
- Staff CRUD (`/api/staff`, `/api/staff/{id}`)
- Staff schedule, documents, leave requests
- Widget preferences (`/api/preferences/widgets`)

Frontend wiring: `Settings.tsx`, `Dentists.tsx`, `DentistProfile.tsx`

---

### Slice 7 — Platform Admin (last, rough is fine)

Only you use this. Build it last.

Endpoints:
- Clinic management (`/api/platform/clinics`)
- Subscriptions (`/api/platform/subscriptions`)
- Spendings + cost categories
- Platform overview stats

Frontend wiring: `admin/` pages

---

## 6. Frontend Migration Rules

When connecting a slice to the frontend:

1. **Remove the localStorage read/write for that feature from `AppContext.tsx`** — don't leave dead code alongside real API calls.
2. **Replace with `axios` calls** (or `fetch`) — add loading states and error handling.
3. **Don't do a big-bang AppContext refactor** — do it feature by feature as you wire each slice.
4. **Files and avatars**: the prototype stores everything as base64 `dataUrl` in localStorage. The backend accepts multipart file uploads and returns file paths/URLs. Update components accordingly when you hit Patients and Staff.

---

## 7. Key Implementation Notes

### Tenant Identification
Pick one strategy and stick to it. Recommended: send `clinic_slug` in the login payload, store it in the Sanctum token's metadata or a custom claim, and use it in tenancy middleware to switch the DB connection.

### New Tenant Provisioning
When `POST /api/platform/clinics` creates a clinic:
1. Creates row in `clinics` (central DB)
2. stancl/tenancy fires `TenantCreated` event
3. Listener creates `apex_clinic_{slug}` database
4. Listener runs all tenant migrations
5. Listener seeds defaults: clinic_schedules (Mon–Fri 08–17), default treatment_types, planner_categories, empty singleton rows for settings, one super_admin staff member

### Role-Based Access
The frontend has a `PATH_ALLOWLIST` per `ClinicStaffRole`. Mirror this server-side with Laravel Policies/Gates. Key rules:
- Only `super_admin` can manage clinic access levels
- Receptionists cannot delete staff
- Enforce soft-delete guards (can't delete staff with upcoming appointments) server-side, not just in the UI

### Soft Deletes
Use `SoftDeletes` on: `clinics` (central), `staff_members`, `patients` (tenant).

### Invoice PDF
Currently generated client-side with jsPDF. For now this is fine — leave it as-is and move server-side (Laravel DomPDF or Snappy) in a later pass.

---

## 8. Testing

### Library: Pest

Use **Pest** (`pestphp/pest` + `pestphp/pest-plugin-laravel`). It runs on top of PHPUnit so you lose nothing, but the syntax is cleaner and it integrates naturally with Laravel's testing helpers.

### Environments & Database Strategy

```
Local (XAMPP)
├── apex_central          ← dev work + Pest runs here
└── apex_clinic_*         ← auto-created by tenancy (dev and test tenants mixed)

Server
├── apex_central          ← staging: manual QA + Pest runs here before every prod deploy
├── apex_clinic_*         ← staging tenant DBs (auto-created)
│
└── apex_central_prod     ← production, NEVER touched by tests
    apex_clinic_prod_*
```

**Deploy workflow:**
```
Write code locally → Pest green locally →
deploy to staging → Pest green on staging → manual QA →
deploy to production
```

**On data loss during Pest runs:** Pest will wipe and recreate data on every run — that is fine. Locally and on staging the data is fake anyway. The workflow is: run Pest → all green → manually add realistic data → verify in the frontend → move to the next slice. The moment you run Pest again that manual data doesn't matter.

**Production rule:** never run `php artisan test` on the production server. Ever.

---

### Setup (do this in Slice 1, before writing any tests)

**1. Install**
```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

**2. Configure `phpunit.xml`**

Point tests at the same `apex_central` DB you use for dev — no separate test DB needed:
```xml
<env name="DB_DATABASE" value="apex_central"/>
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3306"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value=""/>
```

**3. Configure `Pest.php`**

Set up the base dataset every tenant test needs — a provisioned tenant with a switched DB connection:
```php
// tests/Pest.php
uses(Tests\TestCase::class)->in('Feature');

// Shared helper to create and initialize a test tenant
function createTestTenant(string $slug = 'test-clinic'): \App\Models\Central\Clinic
{
    $clinic = \App\Models\Central\Clinic::create([
        'name' => 'Test Clinic',
        'slug' => $slug,
        'contact_email' => 'test@clinic.com',
        'status' => 'active',
    ]);
    // stancl/tenancy will fire TenantCreated → provisions DB + runs migrations
    return $clinic;
}
```

**4. Use `RefreshDatabase` carefully**

In multi-tenant tests, `RefreshDatabase` only refreshes the central DB by default. For tenant DB tests, you need to explicitly initialize the tenant and run tenant migrations within the test. Handle this in a shared `beforeEach` within each test file:

```php
beforeEach(function () {
    $this->clinic = createTestTenant();
    tenancy()->initialize($this->clinic);
});

afterEach(function () {
    tenancy()->end();
});
```

---

### Test Structure

Mirror the slice structure — one folder per feature area:

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── ClinicAuthTest.php
│   │   └── PlatformAuthTest.php
│   ├── Patients/
│   │   ├── PatientCrudTest.php
│   │   ├── PatientMedicalHistoryTest.php
│   │   └── TeethChartTest.php
│   ├── Appointments/
│   │   └── AppointmentCrudTest.php
│   ├── Treatments/
│   │   └── TreatmentTest.php
│   ├── Billing/
│   │   └── InvoiceTest.php
│   └── Settings/
│       └── ClinicSettingsTest.php
└── Unit/
    └── (pure unit tests for services/helpers if needed)
```

---

### What to Test Per Endpoint

For every endpoint, write tests covering these cases at minimum:

| Case | What to assert |
|------|---------------|
| **Happy path** | Correct status code, correct response shape |
| **Unauthenticated** | Returns `401` |
| **Wrong role** | Returns `403` (e.g. receptionist hitting a super_admin endpoint) |
| **Validation failure** | Returns `422` with error fields |
| **Not found** | Returns `404` for non-existent resource |
| **Cross-tenant isolation** | Staff from Clinic A cannot access Clinic B's data |

The cross-tenant isolation test is the most important one unique to this project. Write it for every resource type.

---

### Example: Clinic Auth Test

```php
// tests/Feature/Auth/ClinicAuthTest.php

beforeEach(function () {
    $this->clinic = createTestTenant();
    tenancy()->initialize($this->clinic);

    $this->staff = \App\Models\Tenant\StaffMember::factory()->create([
        'username' => 'johndoe',
        'login_pin' => bcrypt('1234'),
        'sign_in_method' => 'pin',
        'clinic_access_level' => 'staff',
    ]);
});

afterEach(fn() => tenancy()->end());

it('returns a token on valid login', function () {
    postJson('/api/auth/login', [
        'clinic_slug' => 'test-clinic',
        'username'    => 'johndoe',
        'pin'         => '1234',
    ])
    ->assertOk()
    ->assertJsonStructure(['token', 'staff']);
});

it('rejects invalid credentials', function () {
    postJson('/api/auth/login', [
        'clinic_slug' => 'test-clinic',
        'username'    => 'johndoe',
        'pin'         => 'wrong',
    ])->assertUnauthorized();
});

it('rejects login for unknown clinic', function () {
    postJson('/api/auth/login', [
        'clinic_slug' => 'does-not-exist',
        'username'    => 'johndoe',
        'pin'         => '1234',
    ])->assertUnprocessable();
});

it('returns staff profile on /me', function () {
    $token = $this->staff->createToken('test')->plainTextToken;

    getJson('/api/auth/me', ['Authorization' => "Bearer $token"])
        ->assertOk()
        ->assertJsonPath('data.username', 'johndoe');
});
```

---

### Example: Cross-Tenant Isolation Test

```php
// tests/Feature/Patients/PatientCrudTest.php

it('cannot access patients from another clinic', function () {
    $clinicA = createTestTenant('clinic-a');
    $clinicB = createTestTenant('clinic-b');

    // Create a patient in Clinic A
    tenancy()->initialize($clinicA);
    $patient = \App\Models\Tenant\Patient::factory()->create();
    $staffA  = \App\Models\Tenant\StaffMember::factory()->create();
    $tokenA  = $staffA->createToken('test')->plainTextToken;
    tenancy()->end();

    // Try to access Clinic A's patient using Clinic B's token
    tenancy()->initialize($clinicB);
    $staffB = \App\Models\Tenant\StaffMember::factory()->create();
    $tokenB = $staffB->createToken('test')->plainTextToken;

    getJson("/api/patients/{$patient->id}", ['Authorization' => "Bearer $tokenB"])
        ->assertNotFound(); // patient doesn't exist in Clinic B's DB
    tenancy()->end();
});
```

This test is essentially free — it passes automatically when tenancy is set up correctly, and if it ever fails, you have a critical data leak.

---

### Running Tests

```bash
# Run all tests
php artisan test

# Run a specific file
php artisan test tests/Feature/Auth/ClinicAuthTest.php

# Run with coverage (requires Xdebug or PCOV)
php artisan test --coverage
```

---

### When to Write Tests

Write tests **alongside** each slice, not after. The workflow per endpoint:

1. Write the migration + model
2. Write the controller + route
3. Write the Pest test
4. Run it — confirm green
5. Move to the next endpoint

Don't batch tests to the end of a slice. By the time you finish 10 endpoints and sit down to test them all, you've forgotten the edge cases.

---

## 9. Definition of "Done" for Each Slice

A slice is done when:
- [ ] Migrations exist and run cleanly
- [ ] Eloquent models exist with correct relationships
- [ ] All endpoints return correct data
- [ ] Pest tests written and passing (happy path + auth + role + validation + cross-tenant isolation)
- [ ] Frontend is wired — no more localStorage for this feature
- [ ] Auth + role guards are enforced on the endpoints
- [ ] You can demo the feature end-to-end with a real DB

---

*Last updated: finalized DB/environment strategy, MySQL via XAMPP locally.*
