<?php

use App\Http\Controllers\Api\Clinic\AppointmentController;
use App\Http\Controllers\Api\Clinic\ClinicScheduleController;
use App\Http\Controllers\Api\Clinic\ClinicSettingsController;
use App\Http\Controllers\Api\Clinic\InvoiceController;
use App\Http\Controllers\Api\Clinic\LeaveRequestController;
use App\Http\Controllers\Api\Clinic\PatientAnamnesisController;
use App\Http\Controllers\Api\Clinic\PatientController;
use App\Http\Controllers\Api\Clinic\PatientDocumentController;
use App\Http\Controllers\Api\Clinic\PatientInsightsController;
use App\Http\Controllers\Api\Clinic\PatientMedicalHistoryController;
use App\Http\Controllers\Api\Clinic\PatientMonthlyPlanController;
use App\Http\Controllers\Api\Clinic\PatientPaymentRecordController;
use App\Http\Controllers\Api\Clinic\PatientTeethChartController;
use App\Http\Controllers\Api\Clinic\PatientTreatmentEntryController;
use App\Http\Controllers\Api\Clinic\PatientXrayController;
use App\Http\Controllers\Api\Clinic\StaffController;
use App\Http\Controllers\Api\Clinic\StaffDocumentController;
use App\Http\Controllers\Api\Clinic\SwitchStaffController;
use App\Http\Controllers\Api\Clinic\TreatmentRecordController;
use App\Http\Controllers\Api\Clinic\TreatmentTypeController;
use App\Http\Controllers\Api\Clinic\WidgetPreferenceController;
use App\Http\Controllers\Auth\ClinicAuthController;
use App\Http\Controllers\Auth\PlatformAuthController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\PlatformClinicController;
use App\Http\Controllers\Platform\PlatformCostCategoryController;
use App\Http\Controllers\Platform\PlatformOverviewController;
use App\Http\Controllers\Platform\PlatformServiceController;
use App\Http\Controllers\Platform\PlatformSpendingController;
use App\Http\Controllers\Platform\PlatformSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
})->name('api.health');

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('login', [ClinicAuthController::class, 'login'])
        ->name('login');

    Route::post('logout', [ClinicAuthController::class, 'logout'])
        ->middleware('auth:clinic_session')
        ->name('logout');

    Route::get('me', [ClinicAuthController::class, 'me'])
        ->middleware('auth:clinic_session')
        ->name('me');

    Route::post('switch-staff', [SwitchStaffController::class, 'switchStaff'])
        ->middleware('auth:clinic_session')
        ->name('switch-staff');
});

Route::middleware('auth:clinic_session')->group(function () {
    // Clinic settings
    Route::get('/settings/general', [ClinicSettingsController::class, 'showGeneral'])->name('api.settings.general.show');
    Route::put('/settings/general', [ClinicSettingsController::class, 'updateGeneral'])->name('api.settings.general.update');
    Route::get('/settings/invoice', [ClinicSettingsController::class, 'showInvoice'])->name('api.settings.invoice.show');
    Route::put('/settings/invoice', [ClinicSettingsController::class, 'updateInvoice'])->name('api.settings.invoice.update');
    Route::get('/settings/date-time', [ClinicSettingsController::class, 'showDateTime'])->name('api.settings.date-time.show');
    Route::put('/settings/date-time', [ClinicSettingsController::class, 'updateDateTime'])->name('api.settings.date-time.update');

    // Clinic schedule
    Route::get('/settings/schedule', [ClinicScheduleController::class, 'show'])->name('api.settings.schedule.show');
    Route::put('/settings/schedule', [ClinicScheduleController::class, 'update'])->name('api.settings.schedule.update');

    // Staff
    Route::get('/staff', [StaffController::class, 'index'])->name('api.staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('api.staff.store');
    Route::get('/staff/{staff}/avatar', [StaffController::class, 'avatar'])->name('api.staff.avatar');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('api.staff.show');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('api.staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('api.staff.destroy');

    // Staff documents
    Route::get('/staff/{staff}/documents', [StaffDocumentController::class, 'index'])->name('api.staff.documents.index');
    Route::post('/staff/{staff}/documents', [StaffDocumentController::class, 'store'])->name('api.staff.documents.store');
    Route::delete('/staff/{staff}/documents/{document}', [StaffDocumentController::class, 'destroy'])
        ->name('api.staff.documents.destroy')
        ->scopeBindings();

    // Leave requests
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('api.leave-requests.index');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('api.leave-requests.store');
    Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->name('api.leave-requests.update');
    Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('api.leave-requests.destroy');

    // Widget preferences
    Route::get('/preferences/widgets', [WidgetPreferenceController::class, 'show'])->name('api.preferences.widgets.show');
    Route::put('/preferences/widgets', [WidgetPreferenceController::class, 'update'])->name('api.preferences.widgets.update');

    // Appointment Routes
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('api.appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('api.appointments.store');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('api.appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('api.appointments.destroy');

    // Treatment Type Routes
    Route::get('/treatment-types', [TreatmentTypeController::class, 'index'])->name('api.treatment-types.index');
    Route::post('/treatment-types', [TreatmentTypeController::class, 'store'])->name('api.treatment-types.store');
    Route::put('/treatment-types/{type}', [TreatmentTypeController::class, 'update'])->name('api.treatment-types.update');
    Route::delete('/treatment-types/{type}', [TreatmentTypeController::class, 'destroy'])->name('api.treatment-types.destroy');

    // Treatment Record Routes
    Route::get('/treatment-records', [TreatmentRecordController::class, 'index'])->name('api.treatment-records.index');
    Route::post('/treatment-records', [TreatmentRecordController::class, 'store'])->name('api.treatment-records.store');
    Route::put('/treatment-records/{record}', [TreatmentRecordController::class, 'update'])->name('api.treatment-records.update');
    Route::delete('/treatment-records/{record}', [TreatmentRecordController::class, 'destroy'])->name('api.treatment-records.destroy');

    // Invoice Routes
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('api.invoices.store');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('api.invoices.pdf');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.invoices.show');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('api.invoices.update');

    // Patient Routes
    Route::get('/patients', [PatientController::class, 'index'])->name('api.patients.index');
    Route::post('/patients', [PatientController::class, 'store'])->name('api.patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('api.patients.show');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('api.patients.update');
    Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])->name('api.patients.destroy');

    // Patient Medical History Routes
    Route::get('/patients/{patient}/medical-history', [PatientMedicalHistoryController::class, 'show'])
        ->name('api.patients.medical-history.show');
    Route::put('/patients/{patient}/medical-history', [PatientMedicalHistoryController::class, 'update'])
        ->name('api.patients.medical-history.update');

    // Patient Anamnesis Routes
    Route::get('/patients/{patient}/anamnesis', [PatientAnamnesisController::class, 'show'])
        ->name('api.patients.anamnesis.show');
    Route::put('/patients/{patient}/anamnesis', [PatientAnamnesisController::class, 'update'])
        ->name('api.patients.anamnesis.update');

    // Patient Teeth Chart Routes
    Route::get('/patients/{patient}/teeth-chart', [PatientTeethChartController::class, 'show'])
        ->name('api.patients.teeth-chart.show');
    Route::put('/patients/{patient}/teeth-chart', [PatientTeethChartController::class, 'update'])
        ->name('api.patients.teeth-chart.update');

    // Patient Documents Routes
    Route::get('/patients/{patient}/documents', [PatientDocumentController::class, 'index'])
        ->name('api.patients.documents.index');
    Route::post('/patients/{patient}/documents', [PatientDocumentController::class, 'store'])
        ->name('api.patients.documents.store');
    Route::get('/patients/{patient}/documents/{document}/download', [PatientDocumentController::class, 'download'])
        ->name('api.patients.documents.download')
        ->scopeBindings();
    Route::delete('/patients/{patient}/documents/{document}', [PatientDocumentController::class, 'destroy'])
        ->name('api.patients.documents.destroy')
        ->scopeBindings();

    // Patient X-rays
    Route::get('/patients/{patient}/xrays/{xray}/image', [PatientXrayController::class, 'image'])
        ->name('api.patients.xrays.image')
        ->scopeBindings();
    Route::get('/patients/{patient}/xrays/{xray}/thumbnail', [PatientXrayController::class, 'thumbnail'])
        ->name('api.patients.xrays.thumbnail')
        ->scopeBindings();
    Route::get('/patients/{patient}/xrays', [PatientXrayController::class, 'index'])
        ->name('api.patients.xrays.index');
    Route::post('/patients/{patient}/xrays', [PatientXrayController::class, 'store'])
        ->name('api.patients.xrays.store');
    Route::get('/patients/{patient}/xrays/{xray}', [PatientXrayController::class, 'show'])
        ->name('api.patients.xrays.show')
        ->scopeBindings();
    Route::match(['put', 'patch'], '/patients/{patient}/xrays/{xray}', [PatientXrayController::class, 'update'])
        ->name('api.patients.xrays.update')
        ->scopeBindings();
    Route::delete('/patients/{patient}/xrays/{xray}', [PatientXrayController::class, 'destroy'])
        ->name('api.patients.xrays.destroy')
        ->scopeBindings();

    // Patient Monthly Plans Routes
    Route::get('/patients/{patient}/monthly-plans', [PatientMonthlyPlanController::class, 'index'])
        ->name('api.patients.monthly-plans.index');
    Route::post('/patients/{patient}/monthly-plans', [PatientMonthlyPlanController::class, 'store'])
        ->name('api.patients.monthly-plans.store');
    Route::put('/patients/{patient}/monthly-plans/{plan}', [PatientMonthlyPlanController::class, 'update'])
        ->name('api.patients.monthly-plans.update')
        ->scopeBindings();
    Route::delete('/patients/{patient}/monthly-plans/{plan}', [PatientMonthlyPlanController::class, 'destroy'])
        ->name('api.patients.monthly-plans.destroy')
        ->scopeBindings();

    // Patient Insights Routes
    Route::get('/patients/{patient}/insights', [PatientInsightsController::class, 'show'])
        ->name('api.patients.insights.show');

    // Patient Treatments Routes
    Route::get('/patients/{patient}/treatments', [PatientTreatmentEntryController::class, 'index'])
        ->name('api.patients.treatments.index');
    Route::post('/patients/{patient}/treatments', [PatientTreatmentEntryController::class, 'store'])
        ->name('api.patients.treatments.store');
    Route::put('/patients/{patient}/treatments/{entry}', [PatientTreatmentEntryController::class, 'update'])
        ->name('api.patients.treatments.update')
        ->scopeBindings();
    Route::delete('/patients/{patient}/treatments/{entry}', [PatientTreatmentEntryController::class, 'destroy'])
        ->name('api.patients.treatments.destroy')
        ->scopeBindings();

    // Patient Payments Routes
    Route::get('/patients/{patient}/payments', [PatientPaymentRecordController::class, 'index'])
        ->name('api.patients.payments.index');
    Route::post('/patients/{patient}/payments', [PatientPaymentRecordController::class, 'store'])
        ->name('api.patients.payments.store');
    Route::delete('/patients/{patient}/payments/{payment}', [PatientPaymentRecordController::class, 'destroy'])
        ->name('api.patients.payments.destroy')
        ->scopeBindings();
});

Route::prefix('platform')->group(function () {
    Route::prefix('auth')->name('api.platform.auth.')->group(function () {
        Route::post('login', [PlatformAuthController::class, 'login'])->name('login');

        Route::middleware('auth:platform_session')->group(function () {
            Route::post('logout', [PlatformAuthController::class, 'logout'])->name('logout');

            Route::get('me', [PlatformAuthController::class, 'me'])->name('me');
        });
    });

    Route::middleware('auth:platform_session')->group(function () {
        Route::get('overview', [PlatformOverviewController::class, 'index'])->name('api.platform.overview');

        Route::post('clinics/{clinic}/resend-owner-pin', [PlatformClinicController::class, 'resendOwnerPin'])
            ->name('api.platform.clinics.resend-owner-pin');
        Route::get('clinics/{clinic}/services', [PlatformClinicController::class, 'services'])
            ->name('api.platform.clinics.services.index');
        Route::post('clinics/{clinic}/services', [PlatformClinicController::class, 'enableService'])
            ->name('api.platform.clinics.services.store');
        Route::put('clinics/{clinic}/services/{clinicService}', [PlatformClinicController::class, 'updateService'])
            ->name('api.platform.clinics.services.update');
        Route::get('clinics/{clinic}/usage', [PlatformClinicController::class, 'usage'])
            ->name('api.platform.clinics.usage');
        Route::apiResource('clinics', PlatformClinicController::class)->names([
            'index' => 'api.platform.clinics.index',
            'store' => 'api.platform.clinics.store',
            'show' => 'api.platform.clinics.show',
            'update' => 'api.platform.clinics.update',
            'destroy' => 'api.platform.clinics.destroy',
        ]);

        Route::get('subscriptions/{subscription}/invoices', [PlatformSubscriptionController::class, 'invoices'])
            ->name('api.platform.subscriptions.invoices');
        Route::apiResource('subscriptions', PlatformSubscriptionController::class)->only(['index', 'store', 'update'])->names([
            'index' => 'api.platform.subscriptions.index',
            'store' => 'api.platform.subscriptions.store',
            'update' => 'api.platform.subscriptions.update',
        ]);

        Route::get('services/{service}/usage', [PlatformServiceController::class, 'usage'])
            ->name('api.platform.services.usage');
        Route::get('services/{service}/profitability', [PlatformServiceController::class, 'profitability'])
            ->name('api.platform.services.profitability');
        Route::apiResource('services', PlatformServiceController::class)->names([
            'index' => 'api.platform.services.index',
            'store' => 'api.platform.services.store',
            'update' => 'api.platform.services.update',
            'destroy' => 'api.platform.services.destroy',
        ]);

        Route::apiResource('cost-categories', PlatformCostCategoryController::class)->only(['index', 'store', 'update'])->names([
            'index' => 'api.platform.cost-categories.index',
            'store' => 'api.platform.cost-categories.store',
            'update' => 'api.platform.cost-categories.update',
        ]);

        Route::get('spendings/summary', [PlatformSpendingController::class, 'summary'])
            ->name('api.platform.spendings.summary');
        Route::apiResource('spendings', PlatformSpendingController::class)->except(['show'])->names([
            'index' => 'api.platform.spendings.index',
            'store' => 'api.platform.spendings.store',
            'update' => 'api.platform.spendings.update',
            'destroy' => 'api.platform.spendings.destroy',
        ]);

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('api.platform.audit-log.index');
    });
});
