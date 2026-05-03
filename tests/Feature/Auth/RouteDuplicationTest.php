<?php

use Illuminate\Support\Facades\Route;

it('compiles routes without duplication and resolves the explicit verify-email route', function () {
    $route = Route::getRoutes()->getByName('verification.notice');

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toContain('EmailVerificationPromptController');
});

it('keeps the social onboarding redirect target routable', function () {
    $route = Route::getRoutes()->getByName('register.onboarding');

    expect($route)->not->toBeNull()
        ->and(route('register.onboarding', ['user' => 123], false))->toBe('/register/onboarding/123');
});

it('keeps the buyer favorites surface routable for mobile navigation', function () {
    $route = Route::getRoutes()->getByName('buyer.profile');

    expect($route)->not->toBeNull()
        ->and(route('buyer.profile', [], false))->toBe('/mi-cuenta/favoritos');
});
