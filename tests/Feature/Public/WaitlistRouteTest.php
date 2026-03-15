<?php

use App\Http\Controllers\Public\WaitlistController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('waitlist controller compiles and public route is registered', function () {
    expect(class_exists(WaitlistController::class))->toBeTrue();

    $routes = collect(Route::getRoutes()->getRoutesByName());

    expect($routes->has('public.booking.waitlist.store'))->toBeTrue();
});