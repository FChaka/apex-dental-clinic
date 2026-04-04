<?php

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
        ->middleware('clinic.tenancy')
        ->name('login');

    Route::post('logout', [ClinicAuthController::class, 'logout'])
        ->middleware(['clinic.tenancy', 'sanctum.pat:clinic', 'auth:clinic', 'tenancy.from_token'])
        ->name('logout');

    Route::get('me', [ClinicAuthController::class, 'me'])
        ->middleware(['clinic.tenancy', 'sanctum.pat:clinic', 'auth:clinic', 'tenancy.from_token'])
        ->name('me');

    Route::post('switch-staff', [ClinicAuthController::class, 'switchStaff'])
        ->middleware(['clinic.tenancy', 'sanctum.pat:clinic', 'auth:clinic', 'tenancy.from_token'])
        ->name('switch-staff');
});

Route::prefix('platform/auth')->name('api.platform.auth.')->group(function () {
    Route::post('login', [PlatformAuthController::class, 'login'])->name('login');

    Route::post('logout', [PlatformAuthController::class, 'logout'])
        ->middleware(['sanctum.pat:platform', 'auth:platform'])
        ->name('logout');

    Route::get('me', [PlatformAuthController::class, 'me'])
        ->middleware(['sanctum.pat:platform', 'auth:platform'])
        ->name('me');
});
