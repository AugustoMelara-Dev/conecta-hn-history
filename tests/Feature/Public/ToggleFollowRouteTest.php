<?php

use App\Http\Controllers\Public\ToggleFollowController;
use Illuminate\Support\Facades\Route;

test('toggle follow controller compiles and route is registered', function () {
    expect(class_exists(ToggleFollowController::class))->toBeTrue();

    $routes = collect(Route::getRoutes()->getRoutesByName());
    expect($routes->has('follows.toggle'))->toBeTrue();
});
