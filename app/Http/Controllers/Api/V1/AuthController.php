<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandler;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\V1\AuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuthController - API Authentication
 *
 * THIN CONTROLLER PATTERN: Orchestration only. No Business Logic.
 * REFACTORED: Uses AuthTokenServiceInterface instead of User model.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthTokenServiceInterface $tokenService,
    ) {}

    public function register(
        RegisterRequest $request,
        RegisterUserHandler $handler
    ): JsonResponse {
        $command = $request->toCommand();
        $result = $handler->handle($command);

        // Create token via service (no direct model access)
        $tokenData = $this->tokenService->createTokenForUser($result->id, 'api-token');

        $responsePayload = (object) [
            'user' => $tokenData['user'],
            'accessToken' => $tokenData['token'],
            'redirectUrl' => '/dashboard',
        ];

        return response()->json(new AuthResource($responsePayload), 201);
    }

    public function socialLogin(
        \App\Http\Requests\Auth\SocialLoginRequest $request,
        LoginViaSocialProviderHandler $handler
    ): JsonResponse {
        // ═══════════════════════════════════════════════════════════════════════
        // VALIDATION: Already performed by SocialLoginRequest FormRequest
        // No inline validation needed - data is guaranteed valid and safe
        // ═══════════════════════════════════════════════════════════════════════
        $validated = $request->validated();

        $command = new \App\Application\Auth\Commands\LoginViaSocialProviderCommand(
            providerName: $validated['provider'],
            providerId: $validated['provider_id'],
            token: $validated['token'],
            email: $validated['email'],
            name: $validated['name'] ?? 'User',
            avatarUrl: $validated['avatar'] ?? null,
            accessToken: $validated['token']
        );

        $result = $handler->handle($command);
        $tokenData = $this->tokenService->createTokenForUser($result->userId, 'social-api-token');

        $responsePayload = (object) [
            'user' => $tokenData['user'],
            'accessToken' => $tokenData['token'],
            'redirectUrl' => $result->redirectUrl,
        ];

        return response()->json(new AuthResource($responsePayload), 200);
    }
}
