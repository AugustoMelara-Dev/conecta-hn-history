<?php

declare(strict_types=1);

use App\Filament\Resources\TenantResource;

/**
 * VC-AUDIT-107 Test Suite
 *
 * Ensures impersonation action has rate limiting.
 */
describe('VC-AUDIT-107: Impersonation Rate Limiting', function () {
    it('has rate limiter in impersonation action', function () {
        $reflection = new ReflectionClass(TenantResource::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify rate limiter is present
        expect($source)
            ->toContain('// VC-AUDIT-107: Rate limit impersonation')
            ->and($source)
            ->toContain('RateLimiter::tooManyAttempts($rateLimitKey, 5)')
            ->and($source)
            ->toContain('RateLimiter::hit($rateLimitKey, 3600)');
    });

    it('shows notification when rate limited', function () {
        $reflection = new ReflectionClass(TenantResource::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify notification is shown
        expect($source)
            ->toContain('Límite de seguridad alcanzado')
            ->and($source)
            ->toContain('Demasiados intentos de suplantación');
    });
});
