<?php

declare(strict_types=1);

use App\Contracts\Scope\TenantContextInterface;
use App\Exceptions\TenantMismatchException;
use App\Infrastructure\Auth\SanctumAuthTokenService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear dos tenants separados
    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
    
    // Crear usuario para Tenant A
    $this->userA = User::factory()->create(['name' => 'User A']);
    $this->userA->tenants()->attach($this->tenantA);
    
    // Crear usuario para Tenant B
    $this->userB = User::factory()->create(['name' => 'User B']);
    $this->userB->tenants()->attach($this->tenantB);
});

/**
 * Helper: Crear TenantContext mock para un tenant específico
 */
function mockTenantContext(int $tenantId): TenantContextInterface
{
    $mock = Mockery::mock(TenantContextInterface::class);
    $mock->shouldReceive('getTenantId')->andReturn($tenantId);
    return $mock;
}

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: SUCCESSFUL TOKEN CREATION
// ═══════════════════════════════════════════════════════════════════════════

test('creates token for user belonging to current tenant', function () {
    $context = mockTenantContext($this->tenantA->id);
    $service = new SanctumAuthTokenService($context);

    $result = $service->createTokenForUser($this->userA->id);

    expect($result)->toHaveKeys(['user', 'token']);
    expect($result['user']->id)->toBe($this->userA->id);
    expect($result['token'])->toBeString()->not->toBeEmpty();
});

test('creates token with custom token name', function () {
    $context = mockTenantContext($this->tenantA->id);
    $service = new SanctumAuthTokenService($context);

    $result = $service->createTokenForUser($this->userA->id, 'mobile-app-token');

    expect($result['token'])->toBeString();
    
    // Verificar que el token fue creado con el nombre correcto
    $token = $this->userA->tokens()->where('name', 'mobile-app-token')->first();
    expect($token)->not->toBeNull();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: CROSS-TENANT ACCESS BLOCKED
// ═══════════════════════════════════════════════════════════════════════════

test('throws TenantMismatchException for user of different tenant', function () {
    // Intentar crear token para UserB estando en contexto de TenantA
    $context = mockTenantContext($this->tenantA->id);
    $service = new SanctumAuthTokenService($context);

    expect(fn() => $service->createTokenForUser($this->userB->id))
        ->toThrow(TenantMismatchException::class);
});

test('logs security event on cross-tenant attempt', function () {
    Log::shouldReceive('channel')
        ->with('security')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Cross-Tenant Token Issuance') &&
                   $context['severity'] === 'CRITICAL';
        });

    $context = mockTenantContext($this->tenantA->id);
    $service = new SanctumAuthTokenService($context);

    try {
        $service->createTokenForUser($this->userB->id);
    } catch (TenantMismatchException) {
        // Esperado
    }
});

test('returns HTTP 403 for cross-tenant access', function () {
    $context = mockTenantContext($this->tenantA->id);
    $service = new SanctumAuthTokenService($context);

    try {
        $service->createTokenForUser($this->userB->id);
        $this->fail('Expected TenantMismatchException');
    } catch (TenantMismatchException $e) {
        expect($e->getHttpStatusCode())->toBe(403);
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: NON-EXISTENT USER
// ═══════════════════════════════════════════════════════════════════════════

test('throws ModelNotFoundException for non-existent user', function () {
    $context = mockTenantContext($this->tenantA->id);
    $service = new SanctumAuthTokenService($context);

    expect(fn() => $service->createTokenForUser(999999))
        ->toThrow(ModelNotFoundException::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: NULL TENANT CONTEXT
// ═══════════════════════════════════════════════════════════════════════════

test('throws TenantMismatchException when no tenant context', function () {
    $mock = Mockery::mock(TenantContextInterface::class);
    $mock->shouldReceive('getTenantId')->andReturn(null);
    
    $service = new SanctumAuthTokenService($mock);

    expect(fn() => $service->createTokenForUser($this->userA->id))
        ->toThrow(TenantMismatchException::class, 'Cannot issue token without tenant context');
});
