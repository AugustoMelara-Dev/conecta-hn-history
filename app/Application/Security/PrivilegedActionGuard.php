<?php

declare(strict_types=1);

namespace App\Application\Security;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class PrivilegedActionGuard
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function authorizeImpersonation(
        bool $isSuperAdmin,
        bool $credentialsVerified,
        string $reason,
        bool $mfaVerified = true,
    ): string {
        if (! $isSuperAdmin) {
            throw new AuthorizationException('No tienes autorización para acceder como otro usuario.');
        }

        if (! $credentialsVerified) {
            throw ValidationException::withMessages([
                'current_password' => 'No se pudo confirmar la identidad del administrador.',
            ]);
        }

        if (! $mfaVerified) {
            throw ValidationException::withMessages([
                'current_password' => 'Confirma un segundo factor reciente antes de iniciar soporte temporal.',
            ]);
        }

        $normalizedReason = trim($reason);

        if (mb_strlen($normalizedReason) < 10) {
            throw ValidationException::withMessages([
                'impersonation_reason' => 'Debes registrar un motivo de soporte de al menos 10 caracteres.',
            ]);
        }

        return $normalizedReason;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function authorizePlatformMutation(
        bool $isSuperAdmin,
        bool $credentialsVerified,
        bool $mfaVerified,
        string $reason,
    ): string {
        if (! $isSuperAdmin) {
            throw new AuthorizationException('Platform registry access is restricted.');
        }

        if (! $credentialsVerified) {
            throw ValidationException::withMessages([
                'current_password' => 'The current administrator credentials could not be confirmed.',
            ]);
        }

        if (! $mfaVerified) {
            throw ValidationException::withMessages([
                'current_password' => 'Confirm a recent second factor before changing the registry.',
            ]);
        }

        $normalizedReason = trim($reason);

        if (mb_strlen($normalizedReason) < 10 || mb_strlen($normalizedReason) > 500) {
            throw ValidationException::withMessages([
                'reason' => 'Provide an auditable reason between 10 and 500 characters.',
            ]);
        }

        return $normalizedReason;
    }
}
