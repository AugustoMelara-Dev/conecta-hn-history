<?php

use App\Http\Controllers\Public\AppointmentManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Compatibility Routes
|--------------------------------------------------------------------------
|
| Canonical public acquisition, directory and tenant microsite routes live in
| routes/web.php. Keep this file narrow so it cannot register duplicate route
| names or alternate public truths after the canonical web routes are loaded.
|
*/

Route::get('/directory', static function () {
    $query = request()->getQueryString();

    return redirect('/directorio'.($query ? '?'.$query : ''), 301);
})->name('directory.legacy');

Route::get('/appointments/{appointment}/manage', [AppointmentManagementController::class, 'show'])
    ->name('public.appointment.manage')
    ->middleware('signed');

Route::post('/appointments/{appointment}/cancel', [AppointmentManagementController::class, 'cancel'])
    ->name('public.appointment.cancel')
    ->middleware('signed');
