<?php

use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Decorators\GenerateOtpIdempotencyDecorator;
use App\Application\Shared\Contracts\AtomicIdempotencyInterface;

test('decorator is strictly typed to otp interface', function () {
    // Mock the wrapped handler
    /** @var \Mockery\MockInterface&GenerateOtpHandlerInterface $mockHandler */
    $mockHandler = Mockery::mock(GenerateOtpHandlerInterface::class);
    /** @var \Mockery\MockInterface&AtomicIdempotencyInterface $mockService */
    $mockService = Mockery::mock(AtomicIdempotencyInterface::class);

    // Instantiate new decorator
    $decorator = new GenerateOtpIdempotencyDecorator($mockHandler, $mockService);

    // Assert inheritance/implementation
    expect($decorator)->toBeInstanceOf(GenerateOtpHandlerInterface::class);
});
