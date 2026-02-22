<?php

declare(strict_types=1);

it('is declared final', function (): void {
    $reflection = new \ReflectionClass(\App\Application\Shared\Middleware\ValidationMiddleware::class);
    expect($reflection->isFinal())->toBeTrue();
});
