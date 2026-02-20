<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Shared\Contracts\ValidationServiceInterface;
use App\Domain\Shared\Exceptions\ValidationException;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;

/**
 * LaravelValidationService - Framework Adapter for Validation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER PATTERN: Wraps Laravel's Validator behind a clean interface.   ║
 * ║  Decouples Application Layer decorators from Illuminate dependencies.   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LaravelValidationService implements ValidationServiceInterface
{
    public function __construct(
        private ValidatorFactory $factory,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function validate(array $data, array $rules): void
    {
        $validator = $this->factory->make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
            );
        }
    }
}
