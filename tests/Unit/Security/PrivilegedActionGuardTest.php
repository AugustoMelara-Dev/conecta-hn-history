<?php

declare(strict_types=1);

use App\Application\Security\PrivilegedActionGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('requires the current operator password before impersonation', function (): void {
    $guard = new PrivilegedActionGuard;

    try {
        $guard->authorizeImpersonation(
            isSuperAdmin: true,
            credentialsVerified: false,
            reason: 'Reproducir error reportado por el negocio.',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('current_password');

        return;
    }

    $this->fail('Expected a current password validation error.');
});

it('rejects operators that are not allowlisted super administrators', function (): void {
    $guard = new PrivilegedActionGuard;

    $guard->authorizeImpersonation(
        isSuperAdmin: false,
        credentialsVerified: true,
        reason: 'Reproducir error reportado por el negocio.',
    );
})->throws(AuthorizationException::class);

it('requires an auditable support reason', function (): void {
    $guard = new PrivilegedActionGuard;

    try {
        $guard->authorizeImpersonation(
            isSuperAdmin: true,
            credentialsVerified: true,
            reason: '  ',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('impersonation_reason')
            ->and($exception->errors()['impersonation_reason'][0])
            ->toContain('motivo');

        return;
    }

    $this->fail('Expected an impersonation reason validation error.');
});

it('requires recent multifactor verification', function (): void {
    $guard = new PrivilegedActionGuard;

    try {
        $guard->authorizeImpersonation(
            isSuperAdmin: true,
            credentialsVerified: true,
            reason: 'Reproducir error reportado por el negocio.',
            mfaVerified: false,
        );
    } catch (ValidationException $exception) {
        expect($exception->errors()['current_password'][0])->toContain('segundo factor');

        return;
    }

    $this->fail('Expected a recent MFA validation error.');
});

it('authorizes a normalized platform mutation reason after every guard passes', function (): void {
    $guard = new PrivilegedActionGuard;

    $reason = $guard->authorizePlatformMutation(
        isSuperAdmin: true,
        credentialsVerified: true,
        mfaVerified: true,
        reason: '  Activate a registry candidate after evaluation.  ',
    );

    expect($reason)->toBe('Activate a registry candidate after evaluation.');
});

it('requires current credentials for platform mutations', function (): void {
    $guard = new PrivilegedActionGuard;

    try {
        $guard->authorizePlatformMutation(
            isSuperAdmin: true,
            credentialsVerified: false,
            mfaVerified: true,
            reason: 'Activate a registry candidate after evaluation.',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('current_password');

        return;
    }

    $this->fail('Expected a current password validation error.');
});

it('requires recent multifactor verification for platform mutations', function (): void {
    $guard = new PrivilegedActionGuard;

    try {
        $guard->authorizePlatformMutation(
            isSuperAdmin: true,
            credentialsVerified: true,
            mfaVerified: false,
            reason: 'Activate a registry candidate after evaluation.',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('current_password');

        return;
    }

    $this->fail('Expected a recent MFA validation error.');
});

it('requires an auditable bounded reason for platform mutations', function (): void {
    $guard = new PrivilegedActionGuard;

    try {
        $guard->authorizePlatformMutation(
            isSuperAdmin: true,
            credentialsVerified: true,
            mfaVerified: true,
            reason: 'short',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('reason');

        return;
    }

    $this->fail('Expected a platform mutation reason validation error.');
});

it('returns a normalized reason after every guard passes', function (): void {
    $guard = new PrivilegedActionGuard;

    $reason = $guard->authorizeImpersonation(
        isSuperAdmin: true,
        credentialsVerified: true,
        reason: '  Verificar configuración solicitada por soporte.  ',
    );

    expect($reason)->toBe('Verificar configuración solicitada por soporte.');
});
