<?php

declare(strict_types=1);

it('declares accessToken and refreshToken as sensitive properties', function (): void {
    $command = new \App\Application\Auth\Commands\LoginViaSocialProviderCommand(
        tenantId: 'tenant-1',
        providerName: \App\Domain\Shared\Enums\ProviderName::GOOGLE,
        providerId: 'provider-id',
        email: 'user@example.com',
        name: 'Test User',
        avatarUrl: null,
        accessToken: 'secret-access-token',
        refreshToken: 'secret-refresh-token',
        idempotencyKey: 'idem-key',
    );
    expect($command->getSensitiveProperties())
        ->toContain('accessToken')
        ->toContain('refreshToken');
});

it('implements SensitiveDataInterface', function (): void {
    expect(\App\Application\Auth\Commands\LoginViaSocialProviderCommand::class)
        ->toImplement(\App\Application\Shared\Contracts\SensitiveDataInterface::class);
});
