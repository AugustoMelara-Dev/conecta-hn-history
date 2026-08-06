<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| PR Coverage
|--------------------------------------------------------------------------
*/

// PR1 — Filament 4 migration ratchet (covers all 40 Resources + 16 Pages + 18 Widgets)
uses()->afterEach(function () {
    // Could add telemetry here in production
})->in('Feature/Architecture');
