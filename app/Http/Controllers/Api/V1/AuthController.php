<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  THIN CONTROLLER PATTERN: Orchestration only. No Business Logic.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUserHandler $handler
    ): JsonResponse {
        // 1. Factory
        $command = $request->toCommand();

        // 2. Delegate
        $result = $handler->handle($command);

        // 3. Auto-Login Interaction (Orchestration)
        // Since Result doesn't return Token, we issue one here for UX.
        $user = \App\Models\User::find($result->id);
        $token = $user->createToken('api-token')->plainTextToken;

        // Wrap for Resource
        $responsePayload = (object) [
            'user' => $user,
            'accessToken' => $token,
            'redirectUrl' => '/dashboard',
        ];

        return response()->json(new AuthResource($responsePayload), 201);
    }

    // Note: Social Login endpoint usually requires Request with provider token
    // We assume a SocialLoginRequest exists or we rely on generic Request if not strictly typed yet.
    // Given scope "Refactorizar Controladores", I stick to Register for now, 
    // or if user wants Social Login here, I implement it.
    // The prompt explicitly mentioned "LoginViaSocialProviderHandler".
    // I need a Request that maps to `LoginViaSocialProviderCommand`.
    // I'll assume standard inputs: provider, token.
    
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
            token: $request->input('token'), // Access Token from provider
            email: $request->input('email'),
            name: $request->input('name') ?? 'User',
            avatarUrl: $request->input('avatar'),
            accessToken: $request->input('token') // Redundant but consistent with command
        );

        $result = $handler->handle($command);

        // Fetch User (Result only contains ID)
        $user = \App\Models\User::findOrFail($result->userId);
        
        // Generate Token
        $token = $user->createToken('social-api-token')->plainTextToken;
        
         $responsePayload = (object) [
            'user' => $user,
            'accessToken' => $token,
            'redirectUrl' => $result->redirectUrl,
        ];

        return response()->json(new AuthResource($responsePayload), 200);
    }
}
