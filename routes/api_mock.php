<?php

use Illuminate\Support\Facades\Route;
use App\Infrastructure\Http\Controllers\MockApiController;

/*
|--------------------------------------------------------------------------
| Mock API Routes (Titanium DX)
|--------------------------------------------------------------------------
|
| These routes are served only in LOCAL environments to accelerate frontend
| development by simulating backend responses based on JSON Schemas.
|
*/

if (app()->environment('local')) {
    Route::get('/mock/{schema}', [MockApiController::class, 'handle'])
        ->name('api.mock');
}
