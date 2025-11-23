<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\Auth\OtpController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// OTP Authentication Routes
Route::prefix('otp')->group(function () {
    Route::post('/generate', [OtpController::class, 'generate']);
    Route::post('/verify', [OtpController::class, 'verify']);
});

// Authenticated API routes
Route::middleware('auth:sanctum')->group(function () {
    // Future API endpoints
});

Route::post('/interactions', [InteractionController::class, 'store']);
