<?php

declare(strict_types=1);

namespace App\Application\Auth\Contracts;

use App\Application\Auth\Commands\GenerateOtpCommand;

/**
 * GenerateOtpHandlerInterface - Handler Contract for OTP Generation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LISKOV SUBSTITUTION: Enables Decorator pattern with polymorphism.      ║
 * ║  DEPENDENCY INVERSION: Container binds interface, not concrete class.   ║
 * ║  TESTABILITY: Allows mocking in unit tests without hitting real logic.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface GenerateOtpHandlerInterface
{
    /**
     * Handle OTP generation for the given command.
     *
     * @param GenerateOtpCommand $command The OTP generation request
     * @return void
     * 
     * @throws \App\Domain\Auth\Exceptions\TooManyOtpRequestsException If rate limit exceeded
     */
    public function handle(GenerateOtpCommand $command): void;
}
