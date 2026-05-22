<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Delivery\Grpc\Generated;

interface IdentityServiceInterface
{
    public function RegisterUser(RegisterUserRequest $request): RegisterUserResponse;
}
