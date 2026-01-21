<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\LoginResult;
use App\Application\Common\Decorators\AtomicIdempotencyDecorator;
use App\Application\Common\Contracts\AtomicIdempotencyInterface;

final class LoginViaSocialProviderAtomicDecorator implements LoginViaSocialProviderHandlerInterface
{
    private AtomicIdempotencyDecorator $genericDecorator;

    public function __construct(
        LoginViaSocialProviderHandlerInterface $inner,
        AtomicIdempotencyInterface $service
    ) {
        $this->genericDecorator = new AtomicIdempotencyDecorator($inner, $service);
    }

    public function handle(LoginViaSocialProviderCommand $command): LoginResult
    {
        /** @var LoginResult */
        return $this->genericDecorator->handle($command);
    }
}
