<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Handlers\DeleteUserHandlerInterface;
use App\Application\Auth\Results\DeleteUserResult;
use App\Application\Shared\Common\Decorators\AtomicIdempotencyDecorator;
use App\Application\Shared\Contracts\AtomicIdempotencyInterface;

final readonly class DeleteUserAtomicDecorator implements DeleteUserHandlerInterface
{
    private AtomicIdempotencyDecorator $genericDecorator;

    public function __construct(
        DeleteUserHandlerInterface $inner,
        AtomicIdempotencyInterface $service
    ) {
        $this->genericDecorator = new AtomicIdempotencyDecorator($inner, $service);
    }

    public function handle(DeleteUserCommand $command): DeleteUserResult
    {
        /** @var DeleteUserResult */
        return $this->genericDecorator->handle($command);
    }
}
