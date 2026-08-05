<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

final class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(
        private readonly AuthenticatedSessionRegistry $sessions,
    ) {}

    public function toResponse($request): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $request->session()->regenerate();
            $this->sessions->start($request, $user);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Fortify::redirects('login'));
    }
}
