<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Delivery\Grpc;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Infrastructure\Identity\Delivery\Grpc\Generated\IdentityServiceInterface;
use App\Infrastructure\Identity\Delivery\Grpc\Generated\RegisterUserRequest;
use App\Infrastructure\Identity\Delivery\Grpc\Generated\RegisterUserResponse;
use Illuminate\Support\Str;

/**
 * IdentityServiceController - The Binary Highway Adapter
 * 
 * STRICT RULE: NO BUSINESS LOGIC. TRANSLATION ONLY.
 */
final class IdentityServiceController implements IdentityServiceInterface
{
    public function __construct(
        private readonly RegisterUserHandler $handler
    ) {}

    public function RegisterUser(RegisterUserRequest $request): RegisterUserResponse
    {
        // 1. LIFTING (Translation: Proto -> Domain Command)
        // We map the strict proto types to our Domain Types.
        $command = new RegisterUserCommand(
            name: $request->getName(),
            email: $request->getEmail(),
            password: $request->getPassword(),
            tenantName: null, // Optional in proto? If not in proto, assume null or default
            slug: null, // Let handler generate
            source: 'grpc_internal', // Audit Trail
            idempotencyKey: (string) Str::uuid() // Internal calls might need generated key if not provided
        );

        // 2. EXECUTION (The Traffic Cop)
        $result = $this->handler->handle($command);

        // 3. LOWERING (Translation: Domain Result -> Proto Response)
        $response = new RegisterUserResponse();
        $response->setUserId((string) $result->id);
        
        // Map Result Status to Proto String
        // Assuming Result has logic for "requiresVerification" mapping to a status string
        $status = $result->requiresVerification ? 'pending_verification' : 'active';
        $response->setStatus($status);

        return $response;
    }
}
