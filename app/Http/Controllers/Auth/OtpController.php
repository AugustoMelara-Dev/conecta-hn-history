<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GenerateOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\JsonResponse;

/** HTTP adapter for the current command/handler OTP pipelines. */
final class OtpController extends Controller
{
    public function generate(
        GenerateOtpRequest $request,
        GenerateOtpHandlerInterface $generateHandler,
        TenantRepositoryInterface $tenantRepository,
    ): JsonResponse {
        $validated = $request->validated();
        $tenant = $tenantRepository->findById((string) $validated['tenant_id']);

        if ($tenant === null || ! $tenant->isActive()) {
            return $this->genericGenerationResponse();
        }

        try {
            $generateHandler->handle(new GenerateOtpCommand(
                tenantId: (string) $validated['tenant_id'],
                identifier: (string) $validated['identifier'],
                type: filter_var($validated['identifier'], FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'sms',
                ipAddress: $request->ip() ?? '127.0.0.1',
                userAgent: $request->userAgent() ?? 'Unknown',
                idempotencyKey: (string) $validated['idempotency_key'],
            ));
        } catch (TooManyOtpRequestsException $exception) {
            return response()->json([
                'message' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.',
            ], 429, ['Retry-After' => (string) $exception->retryAfterSeconds]);
        }

        return $this->genericGenerationResponse();
    }

    public function verify(
        VerifyOtpRequest $request,
        VerifyOtpHandlerInterface $verifyHandler,
        AuthTokenServiceInterface $tokenService,
        TenantRepositoryInterface $tenantRepository,
    ): JsonResponse {
        $validated = $request->validated();
        $tenant = $tenantRepository->findById((string) $validated['tenant_id']);

        if ($tenant === null || ! $tenant->isActive()) {
            return $this->genericInvalidCodeResponse();
        }

        try {
            $result = $verifyHandler->handle(new VerifyOtpCommand(
                tenantId: (string) $validated['tenant_id'],
                identifier: (string) $validated['identifier'],
                code: (string) $validated['otp_code'],
                ipAddress: $request->ip() ?? '127.0.0.1',
                idempotencyKey: (string) $validated['idempotency_key'],
            ));
        } catch (InvalidOtpException|OtpExpiredException $exception) {
            return $this->genericInvalidCodeResponse();
        } catch (MaxOtpAttemptsExceededException) {
            return response()->json([
                'message' => 'Demasiados intentos. Solicita un código nuevo.',
            ], 429);
        }

        $tokenData = $result->hasUser()
            ? $tokenService->createTokenForUser(
                $result->user->id(),
                'otp-api-token',
                (string) $validated['tenant_id'],
            )
            : null;

        return response()->json([
            'message' => $result->requiresRegistration
                ? 'Verificación exitosa. Completa tu registro.'
                : 'Autenticación exitosa.',
            'user' => $tokenData['user'] ?? null,
            'access_token' => $tokenData['token'] ?? null,
            'token_type' => $tokenData === null ? null : 'Bearer',
            'requires_registration' => $result->requiresRegistration,
            'redirect' => $result->requiresRegistration ? '/register' : '/app',
        ]);
    }

    private function genericGenerationResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Si los datos son válidos, recibirás un código.',
            'expires_in' => (int) config('auth.otp.expiry_minutes', 10) * 60,
        ], 202);
    }

    private function genericInvalidCodeResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'El código no es válido o ya expiró.',
            'errors' => ['otp_code' => ['El código no es válido o ya expiró.']],
        ], 422);
    }
}
