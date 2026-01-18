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
        Request $request, 
        LoginViaSocialProviderHandler $handler
    ): JsonResponse {
        $request->validate([
            'provider' => 'required|string',
            'token' => 'required|string',
            'email' => 'required|email',
            'name' => 'nullable|string',
            'provider_id' => 'required|string',
            'avatar' => 'nullable|url',
        ]);

        $command = new \App\Application\Auth\Commands\LoginViaSocialProviderCommand(
            providerName: $request->input('provider'),
            providerId: $request->input('provider_id'),
            token: $request->input('token'),
            email: $request->input('email'),
            name: $request->input('name') ?? 'User',
            avatarUrl: $request->input('avatar'),
            accessToken: $request->input('token')
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
