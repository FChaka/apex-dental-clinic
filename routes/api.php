<?php

use App\Http\Controllers\Api\Clinic\AppointmentController;
use App\Http\Controllers\Api\Clinic\PatientAnamnesisController;
use App\Http\Controllers\Api\Clinic\PatientController;
use App\Http\Controllers\Api\Clinic\PatientDocumentController;
use App\Http\Controllers\Api\Clinic\PatientInsightsController;
use App\Http\Controllers\Api\Clinic\PatientMedicalHistoryController;
use App\Http\Controllers\Api\Clinic\PatientMonthlyPlanController;
use App\Http\Controllers\Api\Clinic\PatientTeethChartController;
use App\Http\Controllers\Api\Clinic\SwitchStaffController;
use App\Http\Controllers\Auth\ClinicAuthController;
use App\Http\Controllers\Auth\PlatformAuthController;
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

    Route::post('switch-staff/verify', [SwitchStaffController::class, 'verify'])
        ->middleware('auth:clinic_session')
        ->name('switch-staff.verify');
});

Route::middleware('auth:clinic_session')->group(function () {
    //Appointment Routes
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('api.appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('api.appointments.store');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('api.appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('api.appointments.destroy');

    //Patient Routes
    Route::get('/patients', [PatientController::class, 'index'])->name('api.patients.index');
    Route::post('/patients', [PatientController::class, 'store'])->name('api.patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('api.patients.show');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('api.patients.update');

    Route::get('/patients/{patient}/medical-history', [PatientMedicalHistoryController::class, 'show'])
        ->name('api.patients.medical-history.show');
    Route::put('/patients/{patient}/medical-history', [PatientMedicalHistoryController::class, 'update'])
        ->name('api.patients.medical-history.update');

    Route::get('/patients/{patient}/anamnesis', [PatientAnamnesisController::class, 'show'])
        ->name('api.patients.anamnesis.show');
    Route::put('/patients/{patient}/anamnesis', [PatientAnamnesisController::class, 'update'])
        ->name('api.patients.anamnesis.update');

    Route::get('/patients/{patient}/teeth-chart', [PatientTeethChartController::class, 'show'])
        ->name('api.patients.teeth-chart.show');
    Route::put('/patients/{patient}/teeth-chart', [PatientTeethChartController::class, 'update'])
        ->name('api.patients.teeth-chart.update');

    Route::get('/patients/{patient}/documents', [PatientDocumentController::class, 'index'])
        ->name('api.patients.documents.index');
    Route::post('/patients/{patient}/documents', [PatientDocumentController::class, 'store'])
        ->name('api.patients.documents.store');
    Route::delete('/patients/{patient}/documents/{document}', [PatientDocumentController::class, 'destroy'])
        ->name('api.patients.documents.destroy');

    Route::get('/patients/{patient}/monthly-plans', [PatientMonthlyPlanController::class, 'index'])
        ->name('api.patients.monthly-plans.index');
    Route::post('/patients/{patient}/monthly-plans', [PatientMonthlyPlanController::class, 'store'])
        ->name('api.patients.monthly-plans.store');
    Route::put('/patients/{patient}/monthly-plans/{plan}', [PatientMonthlyPlanController::class, 'update'])
        ->name('api.patients.monthly-plans.update');
    Route::delete('/patients/{patient}/monthly-plans/{plan}', [PatientMonthlyPlanController::class, 'destroy'])
        ->name('api.patients.monthly-plans.destroy');

    Route::get('/patients/{patient}/insights', [PatientInsightsController::class, 'show'])
        ->name('api.patients.insights.show');
});

Route::prefix('platform/auth')->name('api.platform.auth.')->group(function () {
    Route::post('login', [PlatformAuthController::class, 'login'])->name('login');

    Route::post('logout', [PlatformAuthController::class, 'logout'])
        ->middleware('auth:platform_session')
        ->name('logout');

    Route::get('me', [PlatformAuthController::class, 'me'])
        ->middleware('auth:platform_session')
        ->name('me');
});
