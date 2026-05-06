<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\SocialLoginResult;
use App\Application\Common\Contracts\AtomicIdempotencyInterface;
use App\Application\Common\Decorators\AtomicIdempotencyDecorator;

final class LoginViaSocialProviderAtomicDecorator implements LoginViaSocialProviderHandlerInterface
{
    private AtomicIdempotencyDecorator $genericDecorator;

    public function __construct(
        LoginViaSocialProviderHandlerInterface $inner,
        AtomicIdempotencyInterface $service
    ) {
        $this->genericDecorator = new AtomicIdempotencyDecorator($inner, $service);
    }

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        /** @var SocialLoginResult */
        return $this->genericDecorator->handle($command);
    }
}
