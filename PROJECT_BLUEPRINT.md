# APEX — Dental Clinic Management System: Project Blueprint

---

## 1. Project Overview

APEX is a dental clinic management SPA (Single Page Application) that covers the full operational lifecycle of a dental practice: patient management, appointment scheduling, treatment tracking, billing/invoicing, staff management, analytics/reporting, and an AI-powered smile design tool. There is also a **platform admin** section for managing multiple clinics (agency/SaaS model).

The frontend was prototyped ("vibe-coded") in **React + Vite** with all data persisted to the browser's **`localStorage`** (and some in-memory state). The goal of this document is to serve as the blueprint for building a proper **Laravel backend** with a real database.

---

## 2. Tech Stack (Current Frontend)

| Layer | Technology |
|-------|-----------|
| Build tool | Vite 6.x |
| UI framework | React 18.3.x |
| Routing | react-router 7.x |
| Styling | Tailwind CSS 4.x, Emotion |
| Component libraries | Radix UI, MUI, Lucide icons, shadcn-style primitives |
| Forms | react-hook-form |
| Charts | Recharts |
| PDF export | jsPDF + jspdf-autotable |
| Calendar | react-day-picker, date-fns |
| Drag & drop | react-dnd |
| AI/Media | face-api.js, @ffmpeg (WASM), browser-image-compression |
| Backend proxy | Express (port 3001) — only for smile-design AI endpoints |

---

## 3. Project File Structure

```
/
├── index.html                          # Vite HTML entry → /src/main.tsx
├── package.json
├── vite.config.ts                      # Proxies /api → localhost:3001
├── server/                             # Express micro-API (smile design only)
│   ├── index.js
│   └── routes/
│       ├── generate.js                 # POST /api/smile/generate
│       ├── segment.js                  # POST /api/smile/segment
│       └── imageProxy.js              # Image proxy for external URLs
│
├── src/
│   ├── main.tsx                        # React root + ErrorBoundary
│   ├── styles/                         # CSS: fonts, tailwind, theme
│   │
│   ├── app/
│   │   ├── App.tsx                     # AppProvider + RouterProvider
│   │   ├── routes.ts                   # All route definitions
│   │   │
│   │   ├── auth/
│   │   │   ├── clinicSession.ts        # sessionStorage: clinic staff auth
│   │   │   └── platformSession.ts      # sessionStorage: platform admin auth
│   │   │
│   │   ├── context/
│   │   │   └── AppContext.tsx           # ★ CENTRAL STATE — all entities, localStorage persistence
│   │   │
│   │   ├── data/
│   │   │   ├── index.ts                # ★ Seed data + type definitions for all entities
│   │   │   └── adminMock.ts            # Platform admin mock data
│   │   │
│   │   ├── types/
│   │   │   └── roles.ts                # Role definitions + route permissions
│   │   │
│   │   ├── utils/                      # Helpers: currency, dateTime, patientInsights, staffAuth, etc.
│   │   │
│   │   ├── layouts/
│   │   │   ├── RootLayout.tsx          # Bare <Outlet />
│   │   │   ├── MainLayout.tsx          # Sidebar + header + auth guard
│   │   │   └── AdminLayout.tsx         # Admin sidebar + platform auth guard
│   │   │
│   │   ├── components/
│   │   │   ├── ConfirmDeleteDialog.tsx  # Reusable delete confirmation
│   │   │   ├── ManualPaymentForm.tsx    # Payment entry form
│   │   │   ├── MonthlyPlanModal.tsx     # Monthly payment plan CRUD
│   │   │   ├── RecordPaymentModal.tsx   # Quick payment from anywhere
│   │   │   ├── PatientInsightsModal.tsx # Read-only patient analytics
│   │   │   ├── StaffPinModal.tsx        # Staff identity switch via PIN
│   │   │   ├── TreatmentPlannerModal.tsx# Treatment plan → PDF/document
│   │   │   ├── TeethChart.tsx           # Interactive FDI teeth chart
│   │   │   ├── teethChartData.ts        # Static FDI tooth metadata
│   │   │   ├── StatusBadge.tsx
│   │   │   ├── InsightMetricCard.tsx
│   │   │   ├── WidgetWrapper.tsx
│   │   │   ├── RequireApexPlatform.tsx
│   │   │   └── ui/                      # ~50 shadcn-style primitives
│   │   │
│   │   └── pages/
│   │       ├── Landing.tsx              # /
│   │       ├── Login.tsx                # /login
│   │       ├── PlatformLogin.tsx        # /platform/login
│   │       ├── Dashboard.tsx            # /dashboard
│   │       ├── Appointments.tsx         # /appointments (calendar + CRUD)
│   │       ├── Patients.tsx             # /patients (list + create)
│   │       ├── PatientProfile.tsx       # /patients/:id (full profile + edit)
│   │       ├── PatientAppointments.tsx  # /patients/:id/appointments
│   │       ├── PatientTreatments.tsx    # /patients/:id/treatments
│   │       ├── PatientPayments.tsx      # /patients/:id/payments
│   │       ├── Dentists.tsx             # /staff (directory + leave mgmt)
│   │       ├── DentistProfile.tsx       # /staff/:id and /profile
│   │       ├── Treatments.tsx           # /treatments (clinic-level records)
│   │       ├── Billing.tsx              # /billing (invoices list)
│   │       ├── SmilePreview.tsx         # /smile-preview
│   │       ├── ReportsAnalytics.tsx     # /reports
│   │       ├── DailyReport.tsx          # /daily-report
│   │       ├── SocialMedia.tsx          # /social-media
│   │       ├── Settings.tsx             # /settings
│   │       ├── NotFound.tsx             # *
│   │       └── admin/
│   │           ├── AdminOverview.tsx     # /admin
│   │           ├── AdminClinics.tsx      # /admin/clinics
│   │           ├── AdminSubscriptions.tsx# /admin/subscriptions
│   │           └── AdminSpendings.tsx    # /admin/spendings
│   │
│   └── features/
│       └── smile-design/
│           ├── SmileDesignPage.tsx
│           ├── api/
│           │   ├── generate.ts          # POST /api/smile/generate
│           │   └── segment.ts           # POST /api/smile/segment
│           ├── components/              # ImageUploader, ParameterPanel, ResultViewer, etc.
│           ├── hooks/useSmileSession.ts
│           ├── types/smile.types.ts
│           └── utils/                   # faceDetection, imageUtils, promptBuilder
```

---

## 4. How Data Is Currently Stored

### 4.1 localStorage Keys (Primary Persistence)

All managed via `src/app/context/AppContext.tsx`:

| localStorage Key | Entity / Purpose |
|-----------------|-----------------|
| `clinic-patients-list` | Array of all patients (falls back to seed data) |
| `clinic-patient-treatment-entries` | `Record<patientId, PatientTreatmentEntry[]>` — chart-based treatments per patient |
| `clinic-patient-payment-entries` | `Record<patientId, PatientPaymentRecord[]>` — payment ledger per patient |
| `clinic-patient-documents` | `Record<patientId, PatientDocument[]>` — uploaded files per patient |
| `clinic-patient-monthly-plans` | `Record<patientId, MonthlyPaymentPlan[]>` — installment plans per patient |
| `clinic-notifications` | Array of notifications |
| `clinic-treatment-records` | Clinic-level treatment records (completed treatments log) |
| `clinic-staff-members` | Staff roster |
| `clinic-current-staff-id` | Currently selected staff member in header |
| `clinic-leave-requests` | Leave request records |
| `clinic-general-settings` | Clinic name, address, phone, schedule, logo, brand color, currency, VAT |
| `clinic-invoice-settings` | Bank details for invoices |
| `clinic-date-time-settings` | Date format, timezone preferences |
| `clinic-invoices` | All invoices |
| `treatment-planner-materials` | Treatment planner material catalog |
| `treatment-planner-categories` | Treatment planner category catalog |
| `dashboard-widget-order` | Dashboard widget layout preference |
| `reports-widget-order` | Reports widget layout preference |

Additional localStorage managed by `TeethChart.tsx` (per patient):

| Key Pattern | Purpose |
|-------------|---------|
| `teeth-chart-procedures-initial-exam-{patientId}` | Procedures marked on teeth |
| `teeth-chart-surfaces-{patientId}` | Tooth surface states |
| `teeth-chart-notes-{patientId}` | Per-tooth notes |
| `teeth-chart-mode` | Chart display mode |

Additional: `SocialMedia.tsx` uses `clinic_social_media_content`, `adminMock.ts` uses `agency-mock-clinics-v1`.

### 4.2 sessionStorage (Auth Only)

| Key | Purpose |
|-----|---------|
| `clinic-auth-staff-id` | Authenticated staff member ID |
| `apex-platform-session` | Platform admin session flag (`"1"`) |

### 4.3 In-Memory Only (Lost on Refresh)

- **Appointments** — initialized from seed `APPOINTMENTS` but never written to localStorage
- **Clinic treatment types** (pricing catalog) — initialized from `CLINIC_TREATMENT_TYPES_INITIAL`
- **Dark mode toggle**

---

## 5. Multi-Tenant, Multi-Database Architecture

This application uses a **multi-tenant, multi-database** strategy. There are two separate database schemas:

| Database | Name Convention | Purpose | Who Uses It |
|----------|----------------|---------|-------------|
| **Central DB** | `apex_central` | Platform administration — manages tenants (clinics), subscriptions, billing, platform admin accounts, and operational costs | You (the platform owner / admin) |
| **Tenant DB** | `apex_clinic_{slug}` | One per clinic — holds all operational data for that specific dental practice | Clinic staff (dentists, receptionists, etc.) |

### How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│                     APEX PLATFORM (You)                         │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              CENTRAL DATABASE (apex_central)              │  │
│  │                                                           │  │
│  │  platform_admins ── clinics ── subscriptions              │  │
│  │                       │                                   │  │
│  │                       ├── clinic_databases (registry)     │  │
│  │                       └── platform_spendings              │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                      │
│            ┌─────────────┼─────────────┐                        │
│            │             │             │                         │
│            ▼             ▼             ▼                         │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐            │
│  │ apex_clinic_  │ │ apex_clinic_  │ │ apex_clinic_  │           │
│  │ smilecenter   │ │ dentiplus    │ │ oradent      │           │
│  │              │ │              │ │              │            │
│  │ patients     │ │ patients     │ │ patients     │            │
│  │ appointments │ │ appointments │ │ appointments │            │
│  │ staff        │ │ staff        │ │ staff        │            │
│  │ treatments   │ │ treatments   │ │ treatments   │            │
│  │ invoices     │ │ invoices     │ │ invoices     │            │
│  │ ...          │ │ ...          │ │ ...          │            │
│  └──────────────┘ └──────────────┘ └──────────────┘            │
└─────────────────────────────────────────────────────────────────┘
```

**Flow when a new clinic is onboarded:**
1. You create a clinic record in the **central DB** (`clinics` table)
2. Laravel automatically provisions a **new tenant database** (`apex_clinic_{slug}`)
3. All tenant migrations run against the new database (creates all tables from the Tenant DB schema)
4. Optionally, seed default data (default treatment types, planner categories, etc.)

**Flow when a clinic staff member logs in:**
1. Request hits the Laravel app
2. Middleware identifies the tenant (from subdomain, header, or login payload)
3. Middleware looks up the clinic in the **central DB** to find the tenant database name
4. Laravel switches the DB connection to the **tenant database** for all subsequent queries
5. All controllers/models operate within that isolated tenant database

> **Laravel Package Recommendation:** Use [stancl/tenancy](https://tenancyforlaravel.com/) — it handles database creation, migration, seeding, and automatic connection switching out of the box.

---

### 5.1 CENTRAL DATABASE — `apex_central`

This is YOUR database as the platform owner. It holds information about all clinics, their subscriptions, platform-level admin users, and your own operational costs. **One instance, never duplicated.**

#### `platform_admins`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name                VARCHAR(255)
email               VARCHAR(255) UNIQUE
password            VARCHAR(255)             -- bcrypt hashed
remember_token      VARCHAR(100) NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `clinics`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name                VARCHAR(255)
slug                VARCHAR(255) UNIQUE      -- used as DB name suffix: apex_clinic_{slug}
region              VARCHAR(100) NULLABLE
plan                ENUM('Starter', 'Professional', 'Enterprise') DEFAULT 'Starter'
seats               INT DEFAULT 5            -- max staff allowed
status              ENUM('active', 'trial', 'suspended') DEFAULT 'trial'
contact_email       VARCHAR(255)
mrr                 DECIMAL(10,2) DEFAULT 0  -- monthly recurring revenue
db_name             VARCHAR(255)             -- actual database name, e.g. "apex_clinic_smilecenter"
db_host             VARCHAR(255) DEFAULT '127.0.0.1'
db_port             VARCHAR(10) DEFAULT '3306'
trial_ends_at       TIMESTAMP NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE       -- soft delete
```

#### `subscriptions`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
clinic_id           BIGINT UNSIGNED FK → clinics.id
plan                ENUM('Starter', 'Professional', 'Enterprise')
amount              DECIMAL(10,2)
status              ENUM('ok', 'past_due', 'canceled') DEFAULT 'ok'
starts_at           DATE
renews_at           DATE
canceled_at         DATE NULLABLE
payment_method      VARCHAR(50) NULLABLE     -- e.g. 'stripe', 'bank_transfer'
external_id         VARCHAR(255) NULLABLE    -- Stripe subscription ID, etc.
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `subscription_invoices` (Platform billing to clinics)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
subscription_id     BIGINT UNSIGNED FK → subscriptions.id
clinic_id           BIGINT UNSIGNED FK → clinics.id
amount              DECIMAL(10,2)
status              ENUM('paid', 'pending', 'overdue', 'void') DEFAULT 'pending'
issued_at           DATE
paid_at             DATE NULLABLE
due_at              DATE
external_id         VARCHAR(255) NULLABLE    -- Stripe invoice ID, etc.
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `platform_services` (Catalog of all features/services you offer or consume)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
key                 VARCHAR(50) UNIQUE       -- machine key, e.g. 'core', 'sms', 'ai_smile', 'social_media', 'whatsapp'
name                VARCHAR(255)             -- display name, e.g. 'SMS Notifications (Twilio)'
description         TEXT NULLABLE
type                ENUM('core', 'addon')    -- core = included in plan, addon = billed separately
billing_model       ENUM('flat', 'per_unit', 'tiered', 'included') DEFAULT 'included'
                                             -- flat = fixed monthly fee
                                             -- per_unit = charged per SMS, per AI call, etc.
                                             -- tiered = volume-based pricing brackets
                                             -- included = part of the subscription plan, no extra cost
unit_label          VARCHAR(50) NULLABLE     -- e.g. 'SMS', 'AI generation', 'GB' (for per_unit / tiered)
default_unit_price  DECIMAL(10,4) NULLABLE   -- default price per unit if per_unit (e.g. 0.0079 per SMS)
default_flat_price  DECIMAL(10,2) NULLABLE   -- default monthly flat fee if flat
is_active           BOOLEAN DEFAULT true     -- can you currently sell/enable this service?
launched_at         DATE NULLABLE            -- when the service went live (NULL = not yet launched)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Seed examples for beta launch:**

| key | name | type | billing_model | unit_label | launched_at |
|-----|------|------|---------------|------------|-------------|
| `core` | Core Clinic Platform | core | included | NULL | 2026-01-01 |
| `sms` | SMS Notifications (Twilio) | addon | per_unit | SMS | 2026-04-01 |
| `ai_smile` | AI Smile Design | addon | per_unit | generation | NULL |
| `social_media` | Social Media Studio | addon | flat | NULL | NULL |
| `whatsapp` | WhatsApp Business | addon | per_unit | message | NULL |
| `storage` | Extra Storage | addon | tiered | GB | NULL |

#### `clinic_services` (Which services each clinic has enabled + pricing overrides)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
clinic_id           BIGINT UNSIGNED FK → clinics.id ON DELETE CASCADE
service_id          BIGINT UNSIGNED FK → platform_services.id ON DELETE CASCADE
is_enabled          BOOLEAN DEFAULT true
unit_price_override DECIMAL(10,4) NULLABLE   -- NULL = use default from platform_services
flat_price_override DECIMAL(10,2) NULLABLE   -- NULL = use default
monthly_quota       INT UNSIGNED NULLABLE    -- optional cap, e.g. 500 SMS/month (NULL = unlimited)
enabled_at          DATE
disabled_at         DATE NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE(clinic_id, service_id)
```

#### `clinic_usage_records` (Metered usage per clinic per service per month)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
clinic_id           BIGINT UNSIGNED FK → clinics.id ON DELETE CASCADE
service_id          BIGINT UNSIGNED FK → platform_services.id ON DELETE CASCADE
month               CHAR(7)                  -- e.g. '2026-03'
quantity            INT UNSIGNED DEFAULT 0   -- e.g. 347 SMS sent, 12 AI generations
unit_cost           DECIMAL(10,4)            -- price per unit at time of recording
total_cost          DECIMAL(10,2)            -- quantity × unit_cost (denormalized for fast queries)
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE(clinic_id, service_id, month)
```

#### `platform_cost_categories` (Your internal cost categories — extensible)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
key                 VARCHAR(50) UNIQUE       -- e.g. 'hosting', 'salaries', 'marketing', 'legal'
name                VARCHAR(255)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `platform_spendings` (Your operational costs — now linkable to services and categories)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
category_id         BIGINT UNSIGNED FK → platform_cost_categories.id NULLABLE
service_id          BIGINT UNSIGNED FK → platform_services.id NULLABLE  -- NULL = general cost, not service-specific
month               CHAR(7)                  -- e.g. '2026-03'
amount              DECIMAL(10,2)
note                TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

> **How this works together**: When Twilio bills you $47.20 for March, you record a `platform_spendings` row with `service_id` pointing to the "sms" service and `category_id` pointing to "infrastructure". When you want to see profit per service, you compare `clinic_usage_records.total_cost` (what clinics owe you) against `platform_spendings` (what the service costs you). When you add a brand new service in the future, you just insert a row into `platform_services` — no schema changes needed.

#### `audit_log` (Platform-level action trail)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
admin_id            BIGINT UNSIGNED FK → platform_admins.id NULLABLE
clinic_id           BIGINT UNSIGNED FK → clinics.id NULLABLE
action              VARCHAR(100)             -- e.g. 'clinic.created', 'clinic.suspended', 'service.enabled'
description         TEXT NULLABLE
metadata            JSON NULLABLE
created_at          TIMESTAMP
```

### Central DB — Entity Relationships

```
platform_admins              (standalone — your login accounts)

platform_services            (catalog of all features you offer or consume)
  ├── has many   clinic_services          (which clinics have it enabled)
  ├── has many   clinic_usage_records     (metered usage per clinic per month)
  └── has many   platform_spendings       (your costs attributed to this service)

clinics
  ├── has many   subscriptions
  │                 └── has many   subscription_invoices
  ├── has many   clinic_services          → also FK to platform_services
  ├── has many   clinic_usage_records     → also FK to platform_services
  ├── has many   audit_log entries
  └── soft-deletable

platform_cost_categories     (your internal cost taxonomy)
  └── has many   platform_spendings

platform_spendings           → optional FK to platform_services + platform_cost_categories
```

---

### 5.2 TENANT DATABASE — `apex_clinic_{slug}`

This is the **template database** that gets created for every new clinic. It contains ALL the operational data for one dental practice. **No `clinic_id` columns needed** — the entire database IS the clinic. Each clinic gets a full, isolated copy of this schema.

> Every table below lives in the tenant database. Foreign keys reference other tables **within the same tenant database only**.

#### `clinic_settings` (Singleton — one row per clinic)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
clinic_name         VARCHAR(255)
phone               VARCHAR(50) NULLABLE
email               VARCHAR(255) NULLABLE
address             TEXT NULLABLE
website             VARCHAR(255) NULLABLE
business_nr         VARCHAR(100) NULLABLE
city                VARCHAR(100) NULLABLE
zip_code            VARCHAR(20) NULLABLE
facebook_url        VARCHAR(255) NULLABLE
instagram_url       VARCHAR(255) NULLABLE
tiktok_url          VARCHAR(255) NULLABLE
logo_path           VARCHAR(255) NULLABLE    -- file path (stored per-tenant on disk/S3)
brand_color         VARCHAR(7) DEFAULT '#3B82F6'
currency            VARCHAR(10) DEFAULT 'EUR'
default_vat         DECIMAL(5,2) NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `clinic_schedules` (Clinic working hours — 7 rows max)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
day_of_week         TINYINT UNSIGNED         -- 0=Sunday … 6=Saturday
is_open             BOOLEAN DEFAULT true
start_hour          TINYINT UNSIGNED         -- 0–23
end_hour            TINYINT UNSIGNED         -- 0–23
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE(day_of_week)
```

#### `invoice_settings` (Singleton — banking details for patient invoices)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
bank_name           VARCHAR(255) NULLABLE
iban                VARCHAR(50) NULLABLE
swift               VARCHAR(20) NULLABLE
account_holder      VARCHAR(255) NULLABLE
other_details       TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `date_time_settings` (Singleton)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
time_zone_mode      ENUM('automatic', 'manual') DEFAULT 'automatic'
manual_time_zone    VARCHAR(100) NULLABLE
date_format         ENUM('dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy-mm-dd') DEFAULT 'dd/mm/yyyy'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `staff_members`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name                VARCHAR(255)
email               VARCHAR(255)
phone               VARCHAR(50) NULLABLE
avatar_path         VARCHAR(255) NULLABLE
role                ENUM('Dentist', 'Dental Hygienist', 'Receptionist', 'Dental Nurse', 'Other')
clinic_access_level ENUM('super_admin', 'admin', 'staff') DEFAULT 'staff'
specialty           VARCHAR(255) NULLABLE
experience          VARCHAR(100) NULLABLE
status              ENUM('Active', 'On Leave', 'Off Duty') DEFAULT 'Active'
username            VARCHAR(100) UNIQUE
sign_in_method      ENUM('pin', 'password') DEFAULT 'pin'
pin_length          TINYINT UNSIGNED DEFAULT 4   -- 4 or 6
login_pin           VARCHAR(255) NULLABLE        -- hashed
login_password      VARCHAR(255) NULLABLE        -- hashed
color               VARCHAR(7) NULLABLE          -- hex for calendar display
annual_leave_days   INT UNSIGNED NULLABLE
leave_start         DATE NULLABLE
leave_end           DATE NULLABLE
paid_by_percentage  BOOLEAN DEFAULT false
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE           -- soft delete
```

#### `staff_working_schedules`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
staff_id            BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
day_of_week         TINYINT UNSIGNED         -- 0=Sunday … 6=Saturday
is_open             BOOLEAN DEFAULT true
start_hour          TINYINT UNSIGNED
end_hour            TINYINT UNSIGNED

UNIQUE(staff_id, day_of_week)
```

#### `staff_percentage_per_treatment`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
staff_id            BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
treatment_type_id   BIGINT UNSIGNED FK → treatment_types.id ON DELETE CASCADE
percentage          DECIMAL(5,2)

UNIQUE(staff_id, treatment_type_id)
```

#### `staff_documents`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
staff_id            BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
name                VARCHAR(255)
type                ENUM('license', 'diploma', 'certification', 'other')
file_name           VARCHAR(255)
file_path           VARCHAR(255)
uploaded_at         TIMESTAMP
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `leave_requests`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
staff_id            BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
start_date          DATE
end_date            DATE
status              ENUM('Pending', 'Approved', 'Rejected', 'Removed') DEFAULT 'Pending'
note                TEXT NULLABLE
requested_at        TIMESTAMP
responded_at        TIMESTAMP NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `patients`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name                VARCHAR(255)
surname             VARCHAR(255) NULLABLE
fathers_name        VARCHAR(255) NULLABLE
birthday            DATE NULLABLE
gender              ENUM('Male', 'Female', 'Other') NULLABLE
phone               VARCHAR(50) NULLABLE
email               VARCHAR(255) NULLABLE
address             TEXT NULLABLE
city                VARCHAR(100) NULLABLE
personal_number     VARCHAR(50) NULLABLE
blood_type          VARCHAR(10) NULLABLE
avatar_path         VARCHAR(255) NULLABLE
general_notes       TEXT NULLABLE
assigned_dentist_id BIGINT UNSIGNED FK → staff_members.id NULLABLE ON DELETE SET NULL
last_visit          DATE NULLABLE
status              ENUM('Active', 'Inactive') DEFAULT 'Active'
medical_alert       TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE       -- soft delete
```

#### `patient_medical_history` (One-to-one with patient)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE UNIQUE
allergies           JSON NULLABLE            -- string array
conditions          JSON NULLABLE            -- string array
notes               TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `patient_anamnesis` (One-to-one with patient)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE UNIQUE
chief_complaint     TEXT NULLABLE
present_illness     TEXT NULLABLE
current_medications TEXT NULLABLE
previous_surgeries  TEXT NULLABLE
family_history      TEXT NULLABLE
dental_history      TEXT NULLABLE
other               TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `treatment_types` (Pricing/service catalog)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name                VARCHAR(255)
description         TEXT NULLABLE
default_duration    INT UNSIGNED             -- minutes
default_price       DECIMAL(10,2)
vat                 DECIMAL(5,2) NULLABLE
is_active           BOOLEAN DEFAULT true
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `appointments`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
dentist_id          BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
date                DATE
time                TIME
treatment           VARCHAR(255)             -- free text label
status              ENUM('Upcoming', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Upcoming'
notes               TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `treatment_records` (Completed treatment log — clinic-level history)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
dentist_id          BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
name                VARCHAR(255)
description         TEXT NULLABLE
status              ENUM('Completed', 'In Progress') DEFAULT 'In Progress'
date                DATE
duration_minutes    INT UNSIGNED
price               DECIMAL(10,2)
payment_status      ENUM('Paid', 'Pending') DEFAULT 'Pending'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `patient_treatment_entries` (Per-patient chart-based treatment rows)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
treatment_type_id   BIGINT UNSIGNED FK → treatment_types.id ON DELETE RESTRICT
dentist_id          BIGINT UNSIGNED FK → staff_members.id ON DELETE RESTRICT
date                DATE
tooth_number        VARCHAR(10) NULLABLE     -- FDI notation (e.g. '14', '36')
price               DECIMAL(10,2)
amount_paid         DECIMAL(10,2) DEFAULT 0
payment_status      ENUM('Paid', 'Pending') DEFAULT 'Pending'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `invoices`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
invoice_number      VARCHAR(50) UNIQUE       -- auto-generated: e.g. INV-2026-0001
date                DATE
due_date            DATE
amount              DECIMAL(10,2)
vat_rate            DECIMAL(5,2) NULLABLE
status              ENUM('Paid', 'Pending') DEFAULT 'Pending'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `invoice_treatment_entries` (Pivot: which treatment entries are on which invoice)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
invoice_id          BIGINT UNSIGNED FK → invoices.id ON DELETE CASCADE
treatment_entry_id  BIGINT UNSIGNED FK → patient_treatment_entries.id ON DELETE CASCADE

UNIQUE(invoice_id, treatment_entry_id)
```

#### `patient_payment_records`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
date                DATE
amount              DECIMAL(10,2)
method              ENUM('cash', 'card', 'transfer', 'other') NULLABLE
note                TEXT NULLABLE
treatment_id        BIGINT UNSIGNED FK → patient_treatment_entries.id NULLABLE ON DELETE SET NULL
treatment_label     VARCHAR(255) NULLABLE
invoice_id          BIGINT UNSIGNED FK → invoices.id NULLABLE ON DELETE SET NULL
monthly_plan_id     BIGINT UNSIGNED FK → monthly_payment_plans.id NULLABLE ON DELETE SET NULL
is_monthly_plan_payment BOOLEAN DEFAULT false
source              ENUM('treatment', 'manual') DEFAULT 'manual'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `monthly_payment_plans`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
plan_name           VARCHAR(255) NULLABLE
total_amount        DECIMAL(10,2)
months              INT UNSIGNED
interest_percent    DECIMAL(5,2) DEFAULT 0
start_date          DATE NULLABLE
payment_day_of_month TINYINT UNSIGNED NULLABLE   -- 1–31
initial_payment     DECIMAL(10,2) NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `patient_documents`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
name                VARCHAR(255)
file_name           VARCHAR(255)
type                VARCHAR(50)              -- MIME type or category
file_path           VARCHAR(255)             -- stored on disk/S3 per-tenant
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `teeth_chart_data` (Per-patient dental chart)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
tooth_number        VARCHAR(10)              -- FDI number (e.g. '11', '48')
procedure           ENUM('Filling', 'Crown', 'Extraction', 'Root Canal', 'Implant') NULLABLE
is_initial_exam     BOOLEAN DEFAULT false
notes               TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE(patient_id, tooth_number, is_initial_exam)
```

#### `teeth_chart_surfaces`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id ON DELETE CASCADE
tooth_number        VARCHAR(10)
surface_key         VARCHAR(20)              -- e.g. 'occ', 'bucc', 'mes', 'dis', 'ling'
values              JSON                     -- string array of conditions
is_initial_exam     BOOLEAN DEFAULT false
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE(patient_id, tooth_number, surface_key, is_initial_exam)
```

#### `planner_categories`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
label               VARCHAR(255)
sort_order          INT UNSIGNED DEFAULT 0
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `planner_materials`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
category_id         BIGINT UNSIGNED FK → planner_categories.id ON DELETE CASCADE
name                VARCHAR(255)
default_price       DECIMAL(10,2)
treatment_type_id   BIGINT UNSIGNED FK → treatment_types.id NULLABLE ON DELETE SET NULL
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `notifications`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
staff_id            BIGINT UNSIGNED FK → staff_members.id NULLABLE ON DELETE CASCADE
message             TEXT
is_read             BOOLEAN DEFAULT false
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `social_media_items`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name                VARCHAR(255)
caption             TEXT NULLABLE
type                ENUM('image', 'video')
file_path           VARCHAR(255)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `smile_design_sessions`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
patient_id          BIGINT UNSIGNED FK → patients.id NULLABLE ON DELETE SET NULL
patient_photo_path  VARCHAR(255)
mask_path           VARCHAR(255) NULLABLE
parameters          JSON                     -- SmileParameters object
stage               VARCHAR(50)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `smile_design_results`
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
session_id          BIGINT UNSIGNED FK → smile_design_sessions.id ON DELETE CASCADE
image_path          VARCHAR(255)
parameters          JSON
generated_at        TIMESTAMP
```

#### `widget_preferences` (Per-staff UI preferences)
```
id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
staff_id            BIGINT UNSIGNED FK → staff_members.id ON DELETE CASCADE
page                VARCHAR(50)              -- 'dashboard' or 'reports'
widget_order        JSON                     -- ordered array of widget keys
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE(staff_id, page)
```

---

## 6. Entity Relationships Summary

### 6.1 Central DB (`apex_central`)

```
platform_admins          (standalone — your admin accounts)

clinics
  ├── has many   subscriptions
  │                 └── has many   subscription_invoices
  └── has many   audit_log entries

platform_spendings       (standalone — your costs)
```

### 6.2 Tenant DB (`apex_clinic_{slug}`) — per clinic

```
clinic_settings          (singleton row)
clinic_schedules         (7 rows max)
invoice_settings         (singleton row)
date_time_settings       (singleton row)

staff_members
  ├── has many   staff_working_schedules
  ├── has many   staff_documents
  ├── has many   staff_percentage_per_treatment
  ├── has many   leave_requests
  ├── has many   notifications
  ├── has many   widget_preferences
  └── assigned to many patients (via patients.assigned_dentist_id)

patients
  ├── has one    patient_medical_history
  ├── has one    patient_anamnesis
  ├── has many   appointments              → also FK to staff_members (dentist)
  ├── has many   patient_treatment_entries  → also FK to treatment_types, staff_members
  ├── has many   patient_payment_records   → optional FK to treatment_entries, invoices, monthly_plans
  ├── has many   patient_documents
  ├── has many   monthly_payment_plans
  ├── has many   teeth_chart_data
  ├── has many   teeth_chart_surfaces
  └── has many   smile_design_sessions
                    └── has many   smile_design_results

treatment_types          (pricing catalog, referenced by treatment_entries + planner_materials)
treatment_records        (clinic history log → FK to patients, staff_members)

invoices                 → FK to patients
  └── has many   invoice_treatment_entries (pivot → patient_treatment_entries)

planner_categories
  └── has many   planner_materials         → optional FK to treatment_types

social_media_items       (standalone)
```

---

## 7. Laravel API Endpoints

Endpoints are split into two groups matching the two databases:

- **Platform endpoints** (`/api/platform/*`) — hit the **central DB** (`apex_central`). Authenticated via platform admin token.
- **Tenant endpoints** (`/api/*`) — hit the **tenant DB** (`apex_clinic_{slug}`). Authenticated via clinic staff token. A tenancy middleware resolves the correct database before any query runs.

### Tenant Endpoints (7.1 – 7.14, 7.16)

> All endpoints below hit the **tenant database** (`apex_clinic_{slug}`).
> The tenancy middleware resolves the correct DB from the request (subdomain, header, or token).
> Authenticated via the `clinic` guard (staff member token).

### 7.1 Clinic Staff Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Staff login (username + PIN or password) → returns Sanctum token |
| POST | `/api/auth/logout` | Destroy token |
| GET | `/api/auth/me` | Get current authenticated staff member |
| POST | `/api/auth/switch-staff` | Switch active staff within session (verify PIN) |

### 7.2 Dashboard & Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/dashboard/stats` | KPI aggregates (today's appointments, revenue, patient count, etc.) |
| GET | `/api/dashboard/weekly-appointments` | Weekly appointment counts for chart |
| GET | `/api/dashboard/monthly-revenue` | Monthly revenue data for chart |
| GET | `/api/reports/overview` | Reports analytics data (trends, distributions) |
| GET | `/api/reports/daily?date={date}` | Daily report: revenue breakdown by dentist/treatment |

### 7.3 Patients

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/patients` | List patients (search, filter by status, pending payments) |
| POST | `/api/patients` | Create patient |
| GET | `/api/patients/{id}` | Get patient with medical history + anamnesis |
| PUT | `/api/patients/{id}` | Update patient demographics, medical info, anamnesis |
| GET | `/api/patients/{id}/appointments` | List patient's appointments (filter by status) |
| GET | `/api/patients/{id}/treatments` | List patient's treatment entries |
| POST | `/api/patients/{id}/treatments` | Add treatment entry to patient chart |
| PUT | `/api/patients/{id}/treatments/{entryId}` | Update treatment entry (price, amount paid, status) |
| DELETE | `/api/patients/{id}/treatments/{entryId}` | Remove treatment entry |
| GET | `/api/patients/{id}/payments` | List patient's payment records |
| POST | `/api/patients/{id}/payments` | Record a payment (manual or treatment-linked) |
| DELETE | `/api/patients/{id}/payments/{paymentId}` | Remove a payment record |
| GET | `/api/patients/{id}/monthly-plans` | List patient's monthly payment plans |
| POST | `/api/patients/{id}/monthly-plans` | Create monthly payment plan |
| PUT | `/api/patients/{id}/monthly-plans/{planId}` | Update monthly plan |
| DELETE | `/api/patients/{id}/monthly-plans/{planId}` | Remove monthly plan |
| GET | `/api/patients/{id}/documents` | List patient's documents |
| POST | `/api/patients/{id}/documents` | Upload document |
| DELETE | `/api/patients/{id}/documents/{docId}` | Remove document |
| GET | `/api/patients/{id}/teeth-chart` | Get full teeth chart state |
| PUT | `/api/patients/{id}/teeth-chart` | Save teeth chart state (procedures, surfaces, notes) |
| GET | `/api/patients/{id}/insights` | Get computed patient insights/analytics |

### 7.4 Appointments

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/appointments` | List appointments (filter: date range, dentist, status) |
| POST | `/api/appointments` | Create appointment |
| PUT | `/api/appointments/{id}` | Update appointment (reschedule, change status, add notes) |
| DELETE | `/api/appointments/{id}` | Delete appointment |

### 7.5 Staff

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/staff` | List staff members (filter by role) |
| POST | `/api/staff` | Create staff member |
| GET | `/api/staff/{id}` | Get staff profile (schedule, documents, leave info) |
| PUT | `/api/staff/{id}` | Update staff (profile, schedule, credentials, color, pay settings) |
| DELETE | `/api/staff/{id}` | Remove staff member (guard: check upcoming appointments) |
| GET | `/api/staff/{id}/documents` | List staff documents |
| POST | `/api/staff/{id}/documents` | Upload staff document |
| DELETE | `/api/staff/{id}/documents/{docId}` | Remove staff document |

### 7.6 Leave Requests

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/leave-requests` | List all leave requests (filter by status) |
| POST | `/api/leave-requests` | Submit leave request |
| PUT | `/api/leave-requests/{id}` | Update (approve/reject/edit) |
| DELETE | `/api/leave-requests/{id}` | Remove leave request |

### 7.7 Treatment Records (Clinic-level log)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/treatment-records` | List records (search, filter by date, status) |
| POST | `/api/treatment-records` | Create treatment record |
| PUT | `/api/treatment-records/{id}` | Update treatment record |
| DELETE | `/api/treatment-records/{id}` | Delete treatment record |

### 7.8 Billing / Invoices

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/invoices` | List invoices (search, filter: status, period, sort) |
| POST | `/api/invoices` | Create invoice (link to treatment entries) |
| GET | `/api/invoices/{id}` | Get invoice detail (with linked treatments) |
| PUT | `/api/invoices/{id}` | Update invoice (status, amounts) |
| GET | `/api/invoices/{id}/pdf` | Generate and download invoice PDF |

### 7.9 Clinic Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/settings/general` | Get clinic general settings |
| PUT | `/api/settings/general` | Update general settings (name, address, schedule, logo, etc.) |
| GET | `/api/settings/invoice` | Get invoice/banking settings |
| PUT | `/api/settings/invoice` | Update invoice settings |
| GET | `/api/settings/date-time` | Get date/time settings |
| PUT | `/api/settings/date-time` | Update date/time settings |

### 7.10 Treatment Types (Pricing Catalog)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/treatment-types` | List all treatment types |
| POST | `/api/treatment-types` | Create treatment type |
| PUT | `/api/treatment-types/{id}` | Update treatment type |
| DELETE | `/api/treatment-types/{id}` | Delete treatment type |

### 7.11 Planner (Categories & Materials)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/planner/categories` | List planner categories |
| POST | `/api/planner/categories` | Create category |
| PUT | `/api/planner/categories/{id}` | Update category |
| DELETE | `/api/planner/categories/{id}` | Delete category |
| PUT | `/api/planner/categories/reorder` | Reorder categories |
| GET | `/api/planner/materials` | List materials (filter by category) |
| POST | `/api/planner/materials` | Create material |
| PUT | `/api/planner/materials/{id}` | Update material |
| DELETE | `/api/planner/materials/{id}` | Delete material |

### 7.12 Notifications

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/notifications` | List notifications for current staff |
| PUT | `/api/notifications/{id}/read` | Mark notification as read |
| PUT | `/api/notifications/read-all` | Mark all as read |

### 7.13 Social Media

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/social-media` | List social media items |
| POST | `/api/social-media` | Upload/save social media item |
| DELETE | `/api/social-media/{id}` | Delete item |

### 7.14 Smile Design (AI Feature)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/smile/segment` | Segment teeth from patient photo (proxy to AI) |
| POST | `/api/smile/generate` | Generate smile design (proxy to AI) |
| POST | `/api/smile/sessions` | Save smile session |
| GET | `/api/smile/sessions` | List sessions for patient |
| GET | `/api/smile/sessions/{id}` | Get session detail with results |

### 7.15 Platform Admin — Central DB endpoints (your admin panel)

These endpoints query the **central database** (`apex_central`). They are completely separate from clinic operations.

**Auth & Overview**

| Method | Endpoint | Description | Central DB Table |
|--------|----------|-------------|-----------------|
| POST | `/api/platform/login` | Platform admin login | `platform_admins` |
| POST | `/api/platform/logout` | Platform admin logout | — |
| GET | `/api/platform/me` | Current admin profile | `platform_admins` |
| GET | `/api/platform/overview` | Dashboard metrics (total clinics, MRR, active trials, cost vs revenue, etc.) | `clinics`, `subscriptions`, `clinic_usage_records`, `platform_spendings` |

**Clinics**

| Method | Endpoint | Description | Central DB Table |
|--------|----------|-------------|-----------------|
| GET | `/api/platform/clinics` | List all clinics (search, filter by status/plan) | `clinics` |
| POST | `/api/platform/clinics` | Create a new clinic → **provisions tenant DB** | `clinics` + creates `apex_clinic_{slug}` |
| GET | `/api/platform/clinics/{id}` | Get clinic detail (with active services + usage summary) | `clinics`, `clinic_services`, `clinic_usage_records` |
| PUT | `/api/platform/clinics/{id}` | Update clinic (plan, status, seats) | `clinics` |
| DELETE | `/api/platform/clinics/{id}` | Soft-delete / suspend clinic | `clinics` |
| GET | `/api/platform/clinics/{id}/services` | List services enabled for this clinic | `clinic_services` |
| POST | `/api/platform/clinics/{id}/services` | Enable a service for this clinic | `clinic_services` |
| PUT | `/api/platform/clinics/{id}/services/{serviceId}` | Update (pricing override, quota, disable) | `clinic_services` |
| GET | `/api/platform/clinics/{id}/usage` | Usage records for this clinic (filter by month, service) | `clinic_usage_records` |

**Subscriptions & Billing**

| Method | Endpoint | Description | Central DB Table |
|--------|----------|-------------|-----------------|
| GET | `/api/platform/subscriptions` | List all subscriptions | `subscriptions` |
| POST | `/api/platform/subscriptions` | Create subscription for clinic | `subscriptions` |
| PUT | `/api/platform/subscriptions/{id}` | Update subscription (plan change, cancel) | `subscriptions` |
| GET | `/api/platform/subscriptions/{id}/invoices` | List billing invoices for a subscription | `subscription_invoices` |

**Services (your feature/service catalog)**

| Method | Endpoint | Description | Central DB Table |
|--------|----------|-------------|-----------------|
| GET | `/api/platform/services` | List all services (active + upcoming) | `platform_services` |
| POST | `/api/platform/services` | Create a new service | `platform_services` |
| PUT | `/api/platform/services/{id}` | Update service (pricing, status, launch date) | `platform_services` |
| DELETE | `/api/platform/services/{id}` | Deactivate/remove service | `platform_services` |
| GET | `/api/platform/services/{id}/usage` | Aggregated usage across all clinics for this service | `clinic_usage_records` |
| GET | `/api/platform/services/{id}/profitability` | Revenue vs cost for this service | `clinic_usage_records`, `platform_spendings` |

**Spendings (your operational costs)**

| Method | Endpoint | Description | Central DB Table |
|--------|----------|-------------|-----------------|
| GET | `/api/platform/cost-categories` | List cost categories | `platform_cost_categories` |
| POST | `/api/platform/cost-categories` | Create cost category | `platform_cost_categories` |
| PUT | `/api/platform/cost-categories/{id}` | Update cost category | `platform_cost_categories` |
| GET | `/api/platform/spendings` | List spendings (filter by month, category, service) | `platform_spendings` |
| POST | `/api/platform/spendings` | Record a spending (optionally link to service + category) | `platform_spendings` |
| PUT | `/api/platform/spendings/{id}` | Update spending | `platform_spendings` |
| DELETE | `/api/platform/spendings/{id}` | Delete spending | `platform_spendings` |
| GET | `/api/platform/spendings/summary` | Monthly breakdown: total costs, per-service, per-category | `platform_spendings` |

**Audit**

| Method | Endpoint | Description | Central DB Table |
|--------|----------|-------------|-----------------|
| GET | `/api/platform/audit-log` | View platform audit trail | `audit_log` |

### 7.16 Widget Preferences (User-level)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/preferences/widgets` | Get widget order for dashboard/reports |
| PUT | `/api/preferences/widgets` | Save widget order/visibility |

---

## 8. Feature Map Summary

| Module | Pages/Routes | CRUD Operations | Key Notes |
|--------|-------------|-----------------|-----------|
| **Auth** | `/login`, `/platform/login` | Login, logout, switch staff | PIN or password; role-based route access |
| **Dashboard** | `/dashboard` | Read (computed stats) | Drag-reorderable widgets; role-scoped data |
| **Patients** | `/patients`, `/patients/:id`, sub-routes | Create, Read, Update (no delete) | Full profile: demographics, medical, anamnesis, teeth chart, treatments, payments, documents, monthly plans |
| **Appointments** | `/appointments` | Create, Read, Update, Delete | Week calendar view; staff schedule awareness; role-scoped visibility |
| **Staff** | `/staff`, `/staff/:id`, `/profile` | Create, Read, Update, Delete | Schedule, documents, leave, credentials, pay-by-percentage; delete guards |
| **Leave Management** | Embedded in `/staff` | Create, Read, Update, Delete | Approve/reject workflow |
| **Treatment Records** | `/treatments` | Create, Read, Update, Delete | Clinic-level log of completed treatments |
| **Billing** | `/billing` | Read, Preview, PDF export | Invoices created from patient treatment flows; status/period filters |
| **Settings** | `/settings` | Read, Update | General, schedule, invoice branding, date/time, treatment types catalog, planner catalog, user management |
| **Reports** | `/reports`, `/daily-report` | Read (computed) | Analytics widgets, daily breakdown, export |
| **Social Media** | `/social-media` | Create, Read, Delete | Canvas-based asset builder for marketing |
| **Smile Design** | `/smile-preview` | Create (session), Read (results) | AI-powered; proxied via Express to external APIs |
| **Platform Admin** | `/admin/*` | Read, Create (clinics) | Multi-clinic management, subscriptions, spendings |

---

## 9. Key Implementation Notes for Laravel

### 9.1 Multi-Tenant, Multi-Database Setup

1. **Recommended package**: Use [stancl/tenancy v3](https://tenancyforlaravel.com/) for Laravel. It provides:
   - Automatic database creation when a tenant is created
   - Automatic migration running on tenant databases
   - Middleware that switches the DB connection per-request
   - Tenant-aware storage disks, cache, queues
   - Ability to seed default data into new tenant databases

2. **Tenant identification**: The frontend should send the clinic slug (or use a subdomain like `smilecenter.apex.app`). The tenancy middleware looks up the slug in the central `clinics` table, resolves the `db_name`, and switches the connection.

3. **Two auth guards**: Configure Laravel with two guards:
   - `platform` guard — authenticates against `platform_admins` in the central DB
   - `clinic` guard — authenticates against `staff_members` in the tenant DB
   - Use **Laravel Sanctum** for both, with separate token tables (central vs tenant)

4. **Central models vs Tenant models**: In your codebase, keep them separate:
   ```
   app/Models/Central/       ← PlatformAdmin, Clinic, Subscription, etc.
   app/Models/Tenant/        ← Patient, Appointment, StaffMember, Invoice, etc.
   ```
   Central models use the `central` connection. Tenant models use the default (switched) connection.

5. **Database provisioning flow**:
   - POST `/api/platform/clinics` → creates `clinics` row in central DB
   - Tenancy package fires `TenantCreated` event
   - Listener creates the database `apex_clinic_{slug}`
   - Listener runs all tenant migrations
   - Listener runs tenant seeder (default treatment types, planner categories, clinic schedule, etc.)

6. **Migrations split**:
   ```
   database/migrations/central/    ← platform_admins, clinics, subscriptions, etc.
   database/migrations/tenant/     ← staff_members, patients, appointments, etc.
   ```

### 9.2 Authentication

7. **Two completely separate login flows**:
   - Platform admin: email + password → standard Laravel auth
   - Clinic staff: username + PIN or password → custom auth (hash PINs with bcrypt; verify server-side; issue Sanctum token scoped to tenant)
   - The "switch staff" feature (StaffPinModal) re-authenticates within the same tenant

### 9.3 File Storage

8. **Per-tenant file storage**: Use tenant-scoped disks. Each tenant's files go into a separate directory:
   ```
   storage/tenants/{tenant_id}/avatars/
   storage/tenants/{tenant_id}/documents/staff/
   storage/tenants/{tenant_id}/documents/patients/
   storage/tenants/{tenant_id}/social-media/
   storage/tenants/{tenant_id}/smile-design/
   ```
   The frontend currently stores everything as base64 `dataUrl` in localStorage. The backend must accept file uploads and return file paths/URLs instead.

### 9.4 Prototype Bugs to Fix

9. **Appointments are NOT persisted in the prototype** — they reset on page refresh (in-memory only from seed data). The backend must persist them in the `appointments` table.

10. **Treatment types catalog is also in-memory only** — same issue. Must be persisted in `treatment_types`.

11. **Teeth chart data** uses per-patient localStorage keys outside of AppContext. The backend normalizes this properly into `teeth_chart_data` and `teeth_chart_surfaces` tables.

### 9.5 Other Considerations

12. **Invoice PDF generation**: Currently client-side with jsPDF. Consider server-side generation with **Laravel DomPDF** or **Snappy** for consistency and security.

13. **Role-based access**: The frontend defines a `PATH_ALLOWLIST` per `ClinicStaffRole`. Implement matching **Laravel policies/gates** (e.g., receptionists can't delete staff, only `super_admin` manages clinic access levels).

14. **Notifications**: Currently static/seeded. Implement as event-driven using **Laravel Events + Listeners** (e.g., `AppointmentCreated` → notification for the assigned dentist). Consider **Laravel Echo + Pusher/Reverb** for real-time push to the frontend.

15. **Smile Design AI**: The Express server proxies to external AI APIs (Replicate). In Laravel, use `Http::post()` to proxy these calls. This can stay as a separate microservice or be absorbed into the Laravel app.

16. **Soft deletes**: Use `SoftDeletes` on `clinics` (central), `staff_members`, and `patients` (tenant) to prevent accidental data loss. The frontend has delete guards for staff with upcoming appointments — enforce this server-side too.

17. **Seeding new tenants**: When a new clinic database is created, seed it with:
    - Default `clinic_schedules` (Mon–Fri 08:00–17:00, weekends closed)
    - Default `treatment_types` from `CLINIC_TREATMENT_TYPES_INITIAL`
    - Default `planner_categories` and `planner_materials`
    - Empty singleton rows for `clinic_settings`, `invoice_settings`, `date_time_settings`
    - One initial `super_admin` staff member (the clinic owner)
