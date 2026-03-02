<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use Illuminate\Contracts\Validation\Factory as IlluminateFactory;
use Illuminate\Contracts\Validation\Validator;

/**
 * LaravelValidatorFactory — Infrastructure adapter for ValidatorFactoryInterface.
 *
 * Wraps Illuminate's Validation Factory so the Application layer
 * never imports Illuminate contracts directly.
 */
final readonly class LaravelValidatorFactory implements ValidatorFactoryInterface
{
    public function __construct(
        private IlluminateFactory $factory,
    ) {}

    public function make(array $data, array $rules): Validator
    {
        return $this->factory->make($data, $rules);
    }
}
