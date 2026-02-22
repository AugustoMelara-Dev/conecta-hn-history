<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\GenerateOtpAction;
use App\Application\Actions\Auth\VerifyOtpAction;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GenerateOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * OtpController - HTTP Adapter for OTP Authentication (Clean Controller)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero inline validation (delegated to FormRequests)
 * - Zero business logic (delegated to Actions)
 * - Single Responsibility: HTTP translation only
 *
 * PROHIBITED: Mail::, Eloquent queries, direct model instantiation.
 */
final class OtpController extends Controller
{
    public function __construct(
        private GenerateOtpAction $generateAction,
        private VerifyOtpAction $verifyAction,
        private RateLimiter $limiter
    ) {}

    /**
     * Generate and send OTP.
     *
     * POST /auth/otp/generate
     */
    public function generate(GenerateOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->enforceRateLimit($request, 'generate', 3);

        $result = $this->generateAction->execute(
            identifier: $validated['identifier'],
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown'
        );

        return response()->json([
            'message' => 'Código enviado exitosamente.',
            'expires_at' => $result['expires_at'],
        ]);
    }

    /**
     * Verify OTP and authenticate user.
     *
     * POST /auth/otp/verify
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->enforceRateLimit($request, 'verify', 5);

        try {
            // MAP: uuid -> identifier, otp_code -> code for internal Action compatibility
            $result = $this->verifyAction->execute(
                identifier: $validated['uuid'],
                code: $validated['otp_code'],
                ipAddress: $request->ip() ?? '127.0.0.1'
            );

            $this->limiter->clear($this->rateLimitKey($request, 'verify'));

            return response()->json([
                'message' => $result['requires_registration']
                    ? 'Verificación exitosa. Completa tu registro.'
                    : 'Autenticación exitosa.',
                'user' => $result['user'],
                'requires_registration' => $result['requires_registration'],
                'redirect' => $result['redirect'],
            ]);
        } catch (InvalidOtpException|OtpExpiredException $e) {
            throw ValidationException::withMessages(['otp_code' => [$e->getMessage()]]);
        }
    }

    /**
     * Enforce rate limiting for OTP operations.
     */
    private function enforceRateLimit(Request $request, string $action, int $maxAttempts): void
    {
        $key = $this->rateLimitKey($request, $action);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);
            throw ValidationException::withMessages([
                'identifier' => ['Demasiados intentos. Intenta en '.ceil($seconds / 60).' minutos.'],
            ]);
        }

        $this->limiter->hit($key, 3600);
    }

    /**
     * Generate rate limit key.
     */
    private function rateLimitKey(Request $request, string $action): string
    {
        $identifier = $request->input('identifier') ?? $request->input('uuid');

        return "otp-{$action}:{$request->ip()}:{$identifier}";
    }
}
