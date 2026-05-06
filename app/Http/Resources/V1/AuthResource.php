<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AuthResource - Unified Auth Response
 */
final class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Polymorphic: Handle optional wrapping or direct properties
        // The DTOs (RegisterUserResult, SocialLoginResult) usually have `user` and `accessToken`.
        $resource = $this->resource;

        return [
            'user' => [
                'id' => $resource->user->id ?? $resource->id, // Adapt based on DTO structure
                'name' => $resource->user->name ?? $resource->name,
                'email' => $resource->user->email ?? $resource->email,
            ],
            'token' => $resource->accessToken ?? $resource->token ?? null,
            'redirect_to' => $resource->redirectUrl ?? '/dashboard',
        ];
    }
}
