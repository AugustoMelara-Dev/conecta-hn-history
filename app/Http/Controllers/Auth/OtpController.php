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
use App\Models\OneTimePassword;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class OtpController extends Controller
{
    public function __construct(
        private GenerateOtpAction $generateAction,
        private VerifyOtpAction $verifyAction,
        private RateLimiter $limiter,
        private StatefulGuard $guard,
    ) {}

    public function generate(GenerateOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->resolveTenantId($request, $validated['identifier']);

        $this->enforceRateLimit($request, 'generate', 3);

        $result = $this->generateAction->execute(
            $tenantId,
            identifier: $validated['identifier'],
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown',
            idempotencyKey: $request->header('Idempotency-Key')
        );

        return response()->json([
            'message' => $this->magicLinkEnabledForTenant($tenantId)
                ? 'Te enviamos un enlace de acceso y un código de respaldo.'
                : 'Código enviado exitosamente.',
            'expires_at' => $result['expires_at'],
            'tenant_id' => $tenantId,
            'delivery_mode' => $this->magicLinkEnabledForTenant($tenantId)
                ? 'magic_link_and_code'
                : 'code',
        ]);
    }

    public function consume(Request $request, string $otp): RedirectResponse
    {
        if (! (bool) config('auth.otp.magic_link_enabled', false)) {
            abort(404);
        }

        $record = OneTimePassword::query()
            ->whereKey($otp)
            ->where('type', 'email')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record instanceof OneTimePassword || ! $this->magicLinkEnabledForTenant((string) $record->tenant_id)) {
            return redirect()->route('login')->with('status', 'El enlace de acceso ya expiró o no es válido. Solicita uno nuevo.');
        }

        $user = $this->resolveOtpUser($record);
        $identifier = $record->identifier;
        $record->delete();

        if (! $user instanceof User) {
            return redirect()->route('tenant.register', [
                'identifier' => $identifier,
            ])->with('status', 'Validamos tu correo. Completa el registro para crear tu cuenta.');
        }

        $this->guard->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return redirect()->to(AuthRedirector::destinationFor($user));
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->resolveTenantId($request, $validated['identifier']);

        $this->enforceRateLimit($request, 'verify', 5);

        try {
            $result = $this->verifyAction->execute(
                $tenantId,
                identifier: $validated['identifier'],
                code: $validated['otp_code'],
                ipAddress: $request->ip() ?? '127.0.0.1',
                idempotencyKey: $request->header('Idempotency-Key')
            );

            $this->authenticateVerifiedUser($request, $result);

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

    private function resolveTenantId(Request $request, ?string $identifier): string
    {
        $requestedTenantId = $request->input('tenant_id');
        if (is_string($requestedTenantId) && $requestedTenantId !== '') {
            return $requestedTenantId;
        }

        if (is_string($identifier) && filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            $userTenantId = User::query()
                ->where('email', $identifier)
                ->whereNotNull('tenant_id')
                ->value('tenant_id');

            if (is_string($userTenantId) && $userTenantId !== '') {
                return $userTenantId;
            }
        }

        $configuredTenantId = config('auth.otp.platform_tenant_id');
        if (is_string($configuredTenantId) && $configuredTenantId !== ''
            && Tenant::query()->whereKey($configuredTenantId)->exists()) {
            return $configuredTenantId;
        }

        $fallbackTenantId = Tenant::query()
            ->orderBy('created_at')
            ->value('id');

        if (is_string($fallbackTenantId) && $fallbackTenantId !== '') {
            return $fallbackTenantId;
        }

        throw ValidationException::withMessages([
            'identifier' => ['No hay un tenant disponible para enviar códigos OTP.'],
        ]);
    }

    private function magicLinkEnabledForTenant(string $tenantId): bool
    {
        if (! (bool) config('auth.otp.magic_link_enabled', false)) {
            return false;
        }

        $allowlist = config('auth.otp.magic_link_tenant_allowlist', []);
        if (! is_array($allowlist) || $allowlist === []) {
            return true;
        }

        return in_array($tenantId, array_map('strval', $allowlist), true);
    }

    private function resolveOtpUser(OneTimePassword $otp): ?User
    {
        if (is_string($otp->user_id) && $otp->user_id !== '') {
            $user = User::query()->find($otp->user_id);

            if ($user instanceof User) {
                return $user;
            }
        }

        return User::query()
            ->where('email', $otp->identifier)
            ->where('tenant_id', $otp->tenant_id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function authenticateVerifiedUser(Request $request, array $result): void
    {
        $userId = data_get($result, 'user.id');

        if (! is_string($userId) || $userId === '') {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            return;
        }

        $this->guard->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }
}
