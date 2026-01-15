<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Http\Controllers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Infrastructure\Identity\Http\Requests\RegisterUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * RegisterUserController - The Diplomat
 * 
 * Orchestrates HTTP Request -> Domain Command -> HTTP Response.
 * Strictly "Skinny": No business logic allowed.
 */
class RegisterUserController extends Controller
{
    public function __construct(
        private readonly RegisterUserHandlerInterface $handler
    ) {}

    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        // 1. Translation (Request -> Command)
        // Data is already validated by the Shield (FormRequest)
        $command = new RegisterUserCommand(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            tenantName: $request->validated('tenant_name'),
            slug: $request->validated('tenant_domain')
        );

        // 2. Execution (Domain Layer)
        $result = $this->handler->handle($command);

        // 3. Response (Domain Result -> HTTP)
        return new JsonResponse(
            data: [
                'data' => [
                    'type' => 'user_registration',
                    'id' => $result->id,
                    'attributes' => [
                        'email' => $result->email,
                        'tenant_id' => $result->tenantId,
                        'requires_verification' => $result->requiresVerification,
                    ]
                ]
            ],
            status: 201,
            headers: [
                'Location' => route('api.users.show', ['user' => $result->id]) // Placeholder strict convention
            ]
        );
    }
}
