<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Support\Facades\Schema;

final readonly class VerifyOtpAction
{
    public function __construct(
        private VerifyOtpHandlerInterface $handler
    ) {}

    public function execute(
        string $tenantId,
        string $identifier,
        string $code,
        string $ipAddress,
        ?string $idempotencyKey = null
    ): array {
        $result = $this->handler->handle(
            new VerifyOtpCommand(
                tenantId: $tenantId,
                identifier: $identifier,
                code: $code,
                ipAddress: $ipAddress,
                idempotencyKey: $idempotencyKey ?? hash('sha256', $tenantId.'|'.$identifier.'|'.$code),
            )
        );

        $authContext = $this->fallbackAuthContext($result->requiresRegistration, $result->tenantId);

        if ($result->hasUser()) {
            $authenticatedUser = $this->resolveAuthenticatedUser($result->userId);

            if ($authenticatedUser instanceof User) {
                $authContext = AuthRedirector::contextFor($authenticatedUser);
            }
        }

        return [
            'message' => $result->requiresRegistration
                ? 'Verificacion exitosa. Completa tu registro.'
                : 'Autenticacion exitosa.',
            'user' => $result->hasUser()
                ? [
                    'id' => $result->userId,
                    'tenant_id' => $result->tenantId,
                ]
                : null,
            'requires_registration' => $result->requiresRegistration,
            'redirect' => $authContext['redirect_to'],
            'redirect_to' => $authContext['redirect_to'],
            'home_surface' => $authContext['home_surface'],
            'audience' => $authContext['audience'],
        ];
    }

    /**
     * @return array{redirect_to: string, home_surface: string, audience: string}
     */
    private function fallbackAuthContext(bool $requiresRegistration, ?string $tenantId): array
    {
        if ($requiresRegistration) {
            return [
                'redirect_to' => route('tenant.register', [], false),
                'home_surface' => AuthRedirector::SURFACE_BUSINESS_APP,
                'audience' => AuthRedirector::AUDIENCE_OWNER,
            ];
        }

        if (is_string($tenantId) && $tenantId !== '') {
            return [
                'redirect_to' => '/app',
                'home_surface' => AuthRedirector::SURFACE_BUSINESS_APP,
                'audience' => AuthRedirector::AUDIENCE_OWNER,
            ];
        }

        return [
            'redirect_to' => route('directory.index', [], false),
            'home_surface' => AuthRedirector::SURFACE_USER_APP,
            'audience' => AuthRedirector::AUDIENCE_CUSTOMER,
        ];
    }

    private function resolveAuthenticatedUser(?string $userId): ?User
    {
        if (! is_string($userId) || $userId === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('users')) {
                return null;
            }

            $user = User::query()->find($userId);

            return $user instanceof User ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
