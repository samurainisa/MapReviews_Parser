<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationReviewController;
use App\Http\Controllers\OrganizationSettingsController;
use Illuminate\Support\Facades\Route;

// Публичные маршруты
Route::post('/login', [AuthController::class, 'login']);

// Защищённые маршруты (cookie-based Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/organization', [OrganizationSettingsController::class, 'show']);
    Route::post('/organization/settings', [OrganizationSettingsController::class, 'store']);
    Route::post('/organization/refresh', [OrganizationSettingsController::class, 'refresh']);
    Route::get('/organization/reviews', [OrganizationReviewController::class, 'index']);
});
