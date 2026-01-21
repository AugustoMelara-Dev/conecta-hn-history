<?php

declare(strict_types=1);

namespace App\Application\Billing\Validators;

use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Handlers\RequestUpgradeHandlerInterface;
use App\Application\Billing\Results\RequestUpgradeResult;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;

/**
 * RequestUpgradeCommandValidator - Clean Architecture Validation Decorator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ║  GATEWAY AGNOSTIC: Validates intent, not payment provider specifics.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RequestUpgradeCommandValidator implements RequestUpgradeHandlerInterface
{
    public function __construct(
        private RequestUpgradeHandlerInterface $inner,
        private ValidatorFactory $validatorFactory,
    ) {}

    public function handle(RequestUpgradeCommand $command): RequestUpgradeResult
    {
        $this->validatorFactory->make($command->toArray(), [
            // Tenant isolation: Required string (UUID format)
            'tenantId' => ['required', 'string', 'uuid'],

            // Plan identifier: Required, alphanumeric with underscores/hyphens
            'newPlanIdentifier' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/i'],

            // Payment method ID: Optional, must be string if present
            'paymentMethodId' => ['nullable', 'string', 'max:255'],

            // Payment method name: Optional, must be string if present
            'paymentMethod' => ['nullable', 'string', 'max:100'],

            // Idempotency key: Optional, must be valid UUID if present
            'idempotencyKey' => ['nullable', 'string', 'uuid'],
        ])->validate();

        return $this->inner->handle($command);
    }
}
