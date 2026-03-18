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
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class OtpController extends Controller
{
    public function __construct(
        private GenerateOtpAction $generateAction,
        private VerifyOtpAction $verifyAction,
        private RateLimiter $limiter
    ) {}

    public function generate(GenerateOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = (string) ($request->input('tenant_id') ?? 'public');

        $this->enforceRateLimit($request, 'generate', 3);

        $result = $this->generateAction->execute(
            $tenantId,
            identifier: $validated['identifier'],
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown',
            idempotencyKey: $request->header('Idempotency-Key')
        );

        return response()->json([
            'message' => 'Codigo enviado exitosamente.',
            'expires_at' => $result['expires_at'],
        ]);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = (string) ($request->input('tenant_id') ?? 'public');

        $this->enforceRateLimit($request, 'verify', 5);

        try {
            $result = $this->verifyAction->execute(
                $tenantId,
                identifier: $validated['uuid'],
                code: $validated['otp_code'],
                ipAddress: $request->ip() ?? '127.0.0.1',
                idempotencyKey: $request->header('Idempotency-Key')
            );

            $this->limiter->clear($this->rateLimitKey($request, 'verify'));

            return response()->json($result);
        } catch (InvalidOtpException|OtpExpiredException $e) {
            throw ValidationException::withMessages(['otp_code' => [$e->getMessage()]]);
        }
    }

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

    private function rateLimitKey(Request $request, string $action): string
    {
        $identifier = $request->input('identifier') ?? $request->input('uuid');

        return "otp-{$action}:{$request->ip()}:{$identifier}";
    }
}
