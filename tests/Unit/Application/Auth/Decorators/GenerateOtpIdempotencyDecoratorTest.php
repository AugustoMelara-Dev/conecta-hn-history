<?php

use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Decorators\GenerateOtpIdempotencyDecorator;
use App\Application\Shared\Contracts\AtomicIdempotencyInterface;

test('decorator is strictly typed to otp interface', function () {
    // Mock the wrapped handler
    $mockHandler = Mockery::mock(GenerateOtpHandlerInterface::class);
    $mockService = Mockery::mock(AtomicIdempotencyInterface::class);

    // Instantiate new decorator
    $decorator = new GenerateOtpIdempotencyDecorator($mockHandler, $mockService);

    // Assert inheritance/implementation
    expect($decorator)->toBeInstanceOf(GenerateOtpHandlerInterface::class);
});
