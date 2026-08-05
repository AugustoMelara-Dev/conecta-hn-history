<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException;
use App\Domain\Auth\Exceptions\OtpExpiredException;

final readonly class TransactionVerifyOtpHandler implements VerifyOtpHandlerInterface
{
    public function __construct(
        private VerifyOtpHandlerInterface $inner,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        // Expected authentication rejections may mutate attempts or invalidate
        // the OTP. Return them through the transaction boundary so those writes
        // commit, then throw the same safe domain exception to the HTTP adapter.
        $outcome = $this->transactionManager->transaction(function () use ($command): VerifyOtpResult|InvalidOtpException|OtpExpiredException|MaxOtpAttemptsExceededException {
            try {
                return $this->inner->handle($command);
            } catch (InvalidOtpException|OtpExpiredException|MaxOtpAttemptsExceededException $exception) {
                return $exception;
            }
        });

        if ($outcome instanceof InvalidOtpException
            || $outcome instanceof OtpExpiredException
            || $outcome instanceof MaxOtpAttemptsExceededException) {
            throw $outcome;
        }

        return $outcome;
    }
}
