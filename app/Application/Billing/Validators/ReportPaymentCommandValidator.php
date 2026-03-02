<?php

declare(strict_types=1);

namespace App\Application\Billing\Validators;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Handlers\ReportPaymentHandlerInterface;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;

/**
 * ReportPaymentCommandValidator - Clean Architecture Validation Decorator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ║  FINANCIAL PRECISION: Validates amounts as positive integers (cents).   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ReportPaymentCommandValidator implements ReportPaymentHandlerInterface
{
    public function __construct(
        private ReportPaymentHandlerInterface $inner,
        private ValidatorFactoryInterface $validatorFactory,
    ) {}

    public function handle(ReportPaymentCommand $command): ReportPaymentResult
    {
        $this->validatorFactory->validate($command->toArray(), [
            // Tenant isolation: Required string (UUID format)
            'tenantId' => ['required', 'string', 'uuid'],

            // Bank reference: Required, limited to DB column size
            'paymentReference' => ['required', 'string', 'max:255'],

            // Financial amount: Must be positive integer (cents)
            'amountInCents' => ['required', 'integer', 'min:1'],

            // ISO 4217 Currency: Exactly 3 uppercase letters
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],

            // Bank name: Limited to standard DB string column
            'bank' => ['required', 'string', 'max:255'],

            // Idempotency: Optional but must be UUID if present
            'idempotencyKey' => ['nullable', 'uuid'],

            // File path: Reasonable filesystem path limit
            'proofOfPaymentPath' => ['nullable', 'string', 'max:1024'],

            // Notes: Hard limit to prevent Memory Bomb (DoS)
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->inner->handle($command);
    }
}
