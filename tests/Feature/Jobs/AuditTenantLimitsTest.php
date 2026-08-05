<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\Governance\AuditTenantLimits;
use App\Infrastructure\Jobs\Governance\DispatchTenantLimitAudits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use stdClass;
use Tests\TestCase;

final class AuditTenantLimitsTest extends TestCase
{
    use RefreshDatabase;

    private string $userId;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->setAsSystemRoot();
        $this->userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $this->userId,
            'name' => 'Limit Audit Owner',
            'email' => 'limit-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantA = $this->createTenant('Limit Tenant A', true, 1);
        $this->tenantB = $this->createTenant('Limit Tenant B', true, 10);
    }

    public function test_audit_counts_only_the_target_tenants_products(): void
    {
        $this->createProduct($this->tenantA, 'A1');
        $this->createProduct($this->tenantA, 'A2');
        $this->createProduct($this->tenantB, 'B1');
        $this->createProduct($this->tenantB, 'B2');
        $this->createProduct($this->tenantB, 'B3');
        $job = new AuditTenantLimits($this->tenantA);

        self::assertSame([
            'tenant_id' => $this->tenantA,
            'product_count' => 2,
            'product_limit' => 1,
            'over_by' => 1,
            'compliant' => false,
        ], $job->complianceSnapshot());

        $job->handle();
    }

    public function test_worker_middleware_restores_and_cleans_uuid_tenant_context(): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $job = new AuditTenantLimits($this->tenantA);

        $job->middleware()[0]->handle(new stdClass, function () use ($context): void {
            self::assertSame($this->tenantA, (string) $context->id());
            self::assertSame($this->tenantA, (string) app('currentTenant')->getKey());
        });

        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
    }

    public function test_global_coordinator_dispatches_active_tenants_only(): void
    {
        Queue::fake();
        $inactiveTenant = $this->createTenant('Inactive Limit Tenant', false, 1);

        (new DispatchTenantLimitAudits)->handle();

        Queue::assertPushed(AuditTenantLimits::class, 2);
        Queue::assertPushed(
            AuditTenantLimits::class,
            fn (AuditTenantLimits $job): bool => $job->tenantId === $this->tenantA,
        );
        Queue::assertPushed(
            AuditTenantLimits::class,
            fn (AuditTenantLimits $job): bool => $job->tenantId === $this->tenantB,
        );
        Queue::assertNotPushed(
            AuditTenantLimits::class,
            fn (AuditTenantLimits $job): bool => $job->tenantId === $inactiveTenant,
        );
    }

    private function createTenant(string $name, bool $active, int $productLimit): string
    {
        $tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'user_id' => $this->userId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'plan' => 'SEED',
            'is_active' => $active,
            'is_pro' => false,
            'product_limit' => $productLimit,
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 1,
            'ai_reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tenantId;
    }

    private function createProduct(string $tenantId, string $name): int
    {
        return DB::table('products')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => Str::lower($name).'-'.Str::random(6),
            'price' => 10,
            'stock_quantity' => 1,
            'manage_stock' => true,
            'stock_status' => 'in_stock',
            'is_visible' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
