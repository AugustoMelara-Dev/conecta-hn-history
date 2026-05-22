<?php

declare(strict_types=1);

use App\Contracts\Scope\TenantContextInterface;
use App\Exceptions\TenantMismatchException;
use App\Infrastructure\Auth\SanctumAuthTokenService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('personal_access_tokens')) {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
});

/**
 * @return array{userA: User, tenantA: Tenant, userB: User, tenantB: Tenant}
 */
function createSanctumTokenFixture(): array
{
    $userA = User::factory()->create(['name' => 'User A']);
    $tenantA = Tenant::factory()->create([
        'name' => 'Tenant A',
        'user_id' => $userA->id,
    ]);

    $userB = User::factory()->create(['name' => 'User B']);
    $tenantB = Tenant::factory()->create([
        'name' => 'Tenant B',
        'user_id' => $userB->id,
    ]);

    return [
        'userA' => $userA,
        'tenantA' => $tenantA,
        'userB' => $userB,
        'tenantB' => $tenantB,
    ];
}

/**
 * Helper: Crear TenantContext mock para un tenant específico
 */
function mockTenantContext(int|string $tenantId): TenantContextInterface
{
    /** @var TenantContextInterface&Mockery\MockInterface $mock */
    $mock = Mockery::mock(TenantContextInterface::class);
    $mock->shouldReceive('getTenantId')->andReturn($tenantId);

    return $mock;
}

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: SUCCESSFUL TOKEN CREATION
// ═══════════════════════════════════════════════════════════════════════════

test('creates token for user belonging to current tenant', function () {
    $fixture = createSanctumTokenFixture();
    $context = mockTenantContext($fixture['tenantA']->id);
    $service = new SanctumAuthTokenService($context);

    $result = $service->createTokenForUser($fixture['userA']->id);

    expect($result)->toHaveKeys(['user', 'token']);
    expect($result['user']->id)->toBe($fixture['userA']->id);
    expect($result['token'])->toBeString();
    expect($result['token'])->toBeTruthy();
});

test('creates token with custom token name', function () {
    $fixture = createSanctumTokenFixture();
    $context = mockTenantContext($fixture['tenantA']->id);
    $service = new SanctumAuthTokenService($context);

    $result = $service->createTokenForUser($fixture['userA']->id, 'mobile-app-token');

    expect($result['token'])->toBeString();
    
    // Verificar que el token fue creado con el nombre correcto
    $token = $fixture['userA']->tokens()->where('name', 'mobile-app-token')->first();
    expect($token)->toBeObject();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: CROSS-TENANT ACCESS BLOCKED
// ═══════════════════════════════════════════════════════════════════════════

test('throws TenantMismatchException for user of different tenant', function () {
    // Intentar crear token para UserB estando en contexto de TenantA
    $fixture = createSanctumTokenFixture();
    $context = mockTenantContext($fixture['tenantA']->id);
    $service = new SanctumAuthTokenService($context);

    expect(fn() => $service->createTokenForUser($fixture['userB']->id))
        ->toThrow(TenantMismatchException::class);
});

test('logs security event on cross-tenant attempt', function () {
    $fixture = createSanctumTokenFixture();

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

    $context = mockTenantContext($fixture['tenantA']->id);
    $service = new SanctumAuthTokenService($context);

    expect(fn () => $service->createTokenForUser($fixture['userB']->id))
        ->toThrow(TenantMismatchException::class);
});

test('returns HTTP 403 for cross-tenant access', function () {
    $fixture = createSanctumTokenFixture();
    $context = mockTenantContext($fixture['tenantA']->id);
    $service = new SanctumAuthTokenService($context);

    expect(fn () => $service->createTokenForUser($fixture['userB']->id))
        ->toThrow(TenantMismatchException::class);

    expect((new TenantMismatchException())->getHttpStatusCode())->toBe(403);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: NON-EXISTENT USER
// ═══════════════════════════════════════════════════════════════════════════

test('throws ModelNotFoundException for non-existent user', function () {
    $fixture = createSanctumTokenFixture();
    $context = mockTenantContext($fixture['tenantA']->id);
    $service = new SanctumAuthTokenService($context);

    expect(fn() => $service->createTokenForUser(999999))
        ->toThrow(ModelNotFoundException::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: NULL TENANT CONTEXT
// ═══════════════════════════════════════════════════════════════════════════

test('throws TenantMismatchException when no tenant context', function () {
    $fixture = createSanctumTokenFixture();
    /** @var TenantContextInterface&Mockery\MockInterface $mock */
    $mock = Mockery::mock(TenantContextInterface::class);
    $mock->shouldReceive('getTenantId')->andReturn(null);
    
    $service = new SanctumAuthTokenService($mock);

    expect(fn() => $service->createTokenForUser($fixture['userA']->id))
        ->toThrow(TenantMismatchException::class, 'Cannot issue token without tenant context');
});
