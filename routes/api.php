<?php

use App\Http\Controllers\ConsentController;
use App\Http\Controllers\MedicalController;
use App\Http\Controllers\RecommendationsController;
use App\Http\Controllers\TrendsController;
use App\Http\Controllers\VitalSignsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Vital Signs API Routes
|--------------------------------------------------------------------------
|
| Routes for managing vital signs records including CRUD operations,
| analytics, and bulk operations.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Vital Signs Routes
    Route::apiResource('vital-signs', VitalSignsController::class);

    // Additional vital signs endpoints
    Route::get('vital-signs-recent', [VitalSignsController::class, 'recent'])->name('vital-signs.recent');
    Route::get('vital-signs-flagged', [VitalSignsController::class, 'flagged'])->name('vital-signs.flagged');
    Route::get('vital-signs-summary', [VitalSignsController::class, 'summary'])->name('vital-signs.summary');
    Route::get('vital-signs-by-type/{vitalSignTypeId}', [VitalSignsController::class, 'byType'])->name('vital-signs.by-type');
    Route::post('vital-signs-bulk-import', [VitalSignsController::class, 'bulkImport'])->name('vital-signs.bulk-import');

    // Trends Routes
    Route::get('trends', [TrendsController::class, 'index'])->name('trends.index');
    Route::get('trends/{vitalSignTypeId}', [TrendsController::class, 'show'])->name('trends.show');

    // Recommendations Routes
    Route::apiResource('recommendations', RecommendationsController::class, [
        'only' => ['index', 'show'],
    ]);
    Route::post('recommendations/{recommendation}/read', [RecommendationsController::class, 'markAsRead'])->name('recommendations.read');
    Route::post('recommendations/{recommendation}/dismiss', [RecommendationsController::class, 'dismiss'])->name('recommendations.dismiss');

    // Consent Management Routes
    Route::apiResource('consent', ConsentController::class, [
        'only' => ['index', 'store', 'show'],
    ]);
    Route::post('consent/{consent}/revoke', [ConsentController::class, 'revoke'])->name('consent.revoke');
});

/*
|--------------------------------------------------------------------------
| Medical Professional API Routes
|--------------------------------------------------------------------------
|
| Routes for healthcare providers to access authorized patient data.
| These routes require medical professional authentication and proper
| consent validation.
|
*/

Route::middleware(['auth:sanctum', 'role:medical_professional'])->prefix('medical')->name('medical.')->group(function () {
    // Medical professional patient access
    Route::get('patients', [MedicalController::class, 'patients'])->name('patients');
    Route::get('patients/{user}/vital-signs', [MedicalController::class, 'patientVitalSigns'])->name('patients.vital-signs');
});

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Public routes that don't require authentication.
|
*/

// Health check endpoint
Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
})->name('health');

// API version endpoint
Route::get('version', function () {
    return response()->json([
        'api_version' => '1.0.0',
        'laravel_version' => app()->version(),
        'php_version' => PHP_VERSION,
    ]);
})->name('version');
