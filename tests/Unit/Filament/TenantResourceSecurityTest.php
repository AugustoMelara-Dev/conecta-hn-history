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
            ->toContain('RateLimiter::tooManyAttempts($operatorRateLimitKey, 5)')
            ->and($source)
            ->toContain('RateLimiter::tooManyAttempts($operatorIpRateLimitKey, 3)')
            ->and($source)
            ->toContain('RateLimiter::hit($operatorRateLimitKey, 3600)')
            ->and($source)
            ->toContain('RateLimiter::hit($operatorIpRateLimitKey, 3600)');
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

    it('counts attempts before delegating password and MFA checks to the durable grant service', function () {
        $reflection = new ReflectionClass(TenantResource::class);
        $source = file_get_contents($reflection->getFileName());

        expect(strpos($source, 'RateLimiter::hit($operatorRateLimitKey, 3600)'))
            ->toBeLessThan(strpos($source, 'ImpersonationGrantService::class'))
            ->and(strpos($source, 'RateLimiter::hit($operatorIpRateLimitKey, 3600)'))
            ->toBeLessThan(strpos($source, 'ImpersonationGrantService::class'))
            ->and($source)
            ->toContain("Log::channel('security')")
            ->toContain('ImpersonationGrantService::class');
    });

    it('excludes livewire request payloads from telescope', function () {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Providers/TelescopeServiceProvider.php');

        expect($source)
            ->toContain('SensitiveTelemetryGuard::shouldExclude')
            ->toContain("'current_password'");
    });
});
