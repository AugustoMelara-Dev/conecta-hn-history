<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Auth;

use App\Contracts\Scope\TenantContextInterface;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Exceptions\TenantMismatchException;
use App\Infrastructure\Auth\SanctumAuthTokenService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SanctumAuthTokenServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'business_type' => 'retail',
        ]);

        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'business_type' => 'service',
        ]);

        $this->userA = User::factory()->create(['name' => 'User A']);
        $this->userA->tenants()->save($this->tenantA);

        $this->userB = User::factory()->create(['name' => 'User B']);
        $this->userB->tenants()->save($this->tenantB);
    }

    #[Test]
    public function creates_token_for_user_belonging_to_current_tenant(): void
    {
        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $this->tenantA->id));

        $result = $service->createTokenForUser((string) $this->userA->id);

        self::assertArrayHasKey('user', $result);
        self::assertArrayHasKey('token', $result);
        self::assertInstanceOf(User::class, $result['user']);
        self::assertSame((string) $this->userA->id, (string) $result['user']->id);
        self::assertNotSame('', $result['token']);
    }

    #[Test]
    public function creates_token_with_custom_token_name(): void
    {
        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $this->tenantA->id));

        $result = $service->createTokenForUser((string) $this->userA->id, 'mobile-app-token');

        /** @var PersonalAccessToken|null $token */
        $token = $this->userA->tokens()->where('name', 'mobile-app-token')->first();

        self::assertNotNull($token);
    }

    #[Test]
    public function throws_tenant_mismatch_exception_for_user_of_different_tenant(): void
    {
        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $this->tenantA->id));

        $this->expectException(TenantMismatchException::class);

        $service->createTokenForUser((string) $this->userB->id);
    }

    #[Test]
    public function logs_security_event_on_cross_tenant_attempt(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('security')
            ->andReturnSelf();

        Log::shouldReceive('critical')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Cross-Tenant Token Issuance')
                    && ($context['severity'] ?? null) === 'CRITICAL';
            });

        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $this->tenantA->id));

        try {
            $service->createTokenForUser((string) $this->userB->id);
            self::fail('Expected TenantMismatchException');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(TenantMismatchException::class, $exception);
        }
    }

    #[Test]
    public function returns_http_403_for_cross_tenant_access(): void
    {
        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $this->tenantA->id));

        try {
            $service->createTokenForUser((string) $this->userB->id);
            self::fail('Expected TenantMismatchException');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(TenantMismatchException::class, $exception);
            self::assertSame(403, $exception->getHttpStatusCode());
        }
    }

    #[Test]
    public function throws_user_not_found_exception_for_non_existent_user(): void
    {
        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $this->tenantA->id));

        $this->expectException(UserNotFoundException::class);

        $service->createTokenForUser('999999');
    }

    #[Test]
    public function throws_tenant_mismatch_exception_when_no_tenant_context_is_available(): void
    {
        $context = new class implements TenantContextInterface
        {
            public function getTenantUuid(): ?string
            {
                return null;
            }
        };

        $service = new SanctumAuthTokenService($context);

        try {
            $service->createTokenForUser((string) $this->userA->id);
            self::fail('Expected TenantMismatchException');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(TenantMismatchException::class, $exception);
            self::assertSame('Cannot issue token without tenant context', $exception->getMessage());
        }
    }

    #[Test]
    public function does_not_expose_tokens_across_tenant_boundaries(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userA->tenants()->save($tenantA);
        $userB->tenants()->save($tenantB);

        $service = new SanctumAuthTokenService($this->mockTenantContext((string) $tenantA->id));
        $service->createTokenForUser((string) $userA->id);

        self::assertFalse(
            PersonalAccessToken::query()->where('tokenable_id', (string) $userB->id)->exists()
        );
    }

    private function mockTenantContext(string|int|null $tenantId): TenantContextInterface
    {
        return new class($tenantId) implements TenantContextInterface
        {
            public function __construct(private readonly string|int|null $tenantId) {}

            public function getTenantUuid(): ?string
            {
                return $this->tenantId !== null ? (string) $this->tenantId : null;
            }
        };
    }
}
