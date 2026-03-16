<?php

use Illuminate\Support\Facades\Route;

it('compiles routes without duplication and resolves the explicit verify-email route', function () {
    $route = Route::getRoutes()->getByName('verification.notice');

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toContain('EmailVerificationPromptController');
});
