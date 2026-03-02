<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use App\Application\Shared\Exceptions\CommandValidationException;
use Illuminate\Contracts\Validation\Factory as IlluminateFactory;
use Illuminate\Validation\ValidationException;

/**
 * LaravelValidatorFactory — Infrastructure adapter for ValidatorFactoryInterface.
 *
 * Wraps Illuminate's Validation Factory so the Application layer
 * never imports Illuminate contracts directly.
 * Throws CommandValidationException on failure — framework type stays in Infrastructure.
 */
final readonly class LaravelValidatorFactory implements ValidatorFactoryInterface
{
    public function __construct(
        private IlluminateFactory $factory,
    ) {}

    public function validate(array $data, array $rules): void
    {
        $validator = $this->factory->make($data, $rules);

        if ($validator->fails()) {
            throw new CommandValidationException($validator->errors()->toArray());
        }
    }
}
