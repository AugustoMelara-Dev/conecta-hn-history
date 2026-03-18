<?php

declare(strict_types=1);

test('actions layer has documentation', function () {
    $readmePath = __DIR__.'/../../app/Actions/README.md';
    expect(file_exists($readmePath))->toBeTrue();
});

test('actions documentation includes architectural drivers', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/README.md');
    expect($content)
        ->toContain('Architectural Drivers')
        ->toContain('Vendor Isolation')
        ->toContain('Microservices Readiness');
});
