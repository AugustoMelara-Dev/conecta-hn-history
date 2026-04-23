<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AuthResource - Unified Auth Response
 */
final class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $rawUser = $resource->user ?? $resource;
        $user = $rawUser instanceof User ? $rawUser : null;

        $fallbackRedirect = route('directory.index', [], false);
        $fallbackHomeSurface = AuthRedirector::SURFACE_USER_APP;
        $fallbackAudience = AuthRedirector::AUDIENCE_CUSTOMER;

        if ($user instanceof User) {
            $fallbackRedirect = AuthRedirector::destinationFor($user);
            $fallbackHomeSurface = AuthRedirector::homeSurfaceFor($user);
            $fallbackAudience = AuthRedirector::audienceFor($user);
        }

        $redirectTo = $resource->redirect_to
            ?? $resource->redirectUrl
            ?? $fallbackRedirect;

        $homeSurface = $resource->home_surface
            ?? $resource->homeSurface
            ?? $fallbackHomeSurface;

        $audience = $resource->audience
            ?? $fallbackAudience;

        return [
            'user' => [
                'id' => $resource->user->id ?? $resource->id, // Adapt based on DTO structure
                'name' => $resource->user->name ?? $resource->name,
                'email' => $resource->user->email ?? $resource->email,
            ],
            'token' => $resource->accessToken ?? $resource->token ?? null,
            'redirect_to' => $redirectTo,
            'home_surface' => $homeSurface,
            'audience' => $audience,
        ];
    }
}
