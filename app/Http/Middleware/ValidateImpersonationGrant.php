<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Identity\ImpersonationGrantService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ValidateImpersonationGrant
{
    public function __construct(
        private ImpersonationGrantService $impersonation,
    ) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        try {
            $state = $this->impersonation->state($request);
            if ($state === 'none' || $state === 'active') {
                return $next($request);
            }

            $this->impersonation->restoreOperator($request, $state);

            return redirect('/admin')->with(
                'warning',
                $state === 'expired'
                    ? 'La sesión temporal de soporte expiró.'
                    : 'La sesión temporal de soporte fue cerrada.',
            );
        } catch (Throwable) {
            $this->impersonation->failClosed($request);

            return redirect('/login')->withErrors([
                'email' => 'La sesión de soporte no pudo validarse. Inicia sesión nuevamente.',
            ]);
        }
    }
}
