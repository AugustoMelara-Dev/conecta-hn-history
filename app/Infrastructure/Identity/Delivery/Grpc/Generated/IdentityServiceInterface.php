<?php

namespace App\Infrastructure\Identity\Delivery\Grpc\Generated;

interface IdentityServiceInterface
{
    public function RegisterUser(RegisterUserRequest $request): RegisterUserResponse;
}
