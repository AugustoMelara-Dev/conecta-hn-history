<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Application\Orders\CanonicalOrderWriter;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\Governance\DispatchStaleOrderCleanup;
use App\Infrastructure\Jobs\Governance\ReleaseStaleOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use stdClass;
use Tests\TestCase;

final class ReleaseStaleOrdersTenantIsolationTest extends TestCase
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
            'name' => 'Stale Order Owner',
            'email' => 'stale-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantA = $this->createTenant('Tenant A', true);
        $this->tenantB = $this->createTenant('Tenant B', true);
    }

    public function test_tenant_worker_cancels_only_its_stale_pending_orders_through_the_fsm(): void
    {
        $staleA = $this->createOrder($this->tenantA, now()->subMinutes(31));
        $freshA = $this->createOrder($this->tenantA, now()->subMinutes(29));
        $staleB = $this->createOrder($this->tenantB, now()->subHour());

        (new ReleaseStaleOrders($this->tenantA))->handle(app(CanonicalOrderWriter::class));

        $cancelled = DB::table('orders')->where('id', $staleA)->first();
        self::assertSame('cancelled', $cancelled->status);
        self::assertSame('stale_order', $cancelled->cancellation_reason);
        self::assertNotNull($cancelled->cancelled_at);
        self::assertSame('pending', DB::table('orders')->where('id', $freshA)->value('status'));
        self::assertSame('pending', DB::table('orders')->where('id', $staleB)->value('status'));
        $message = DB::table('outbox_messages')
            ->where('type', BusinessOperationChanged::class)
            ->first();
        self::assertNotNull($message);
        $payload = json_decode((string) $message->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($this->tenantA, $payload['data']['tenantId']);
        self::assertSame('order:'.$staleA, $payload['data']['aggregateId']);
        self::assertSame('order.cancelled', $payload['data']['eventName']);
        self::assertSame('system', $payload['data']['actorId']);
    }

    public function test_worker_middleware_restores_and_cleans_uuid_tenant_context(): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $job = new ReleaseStaleOrders($this->tenantA);

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
        $inactiveTenant = $this->createTenant('Inactive Tenant', false);

        (new DispatchStaleOrderCleanup)->handle();

        Queue::assertPushed(ReleaseStaleOrders::class, 2);
        Queue::assertPushed(
            ReleaseStaleOrders::class,
            fn (ReleaseStaleOrders $job): bool => $job->tenantId === $this->tenantA,
        );
        Queue::assertPushed(
            ReleaseStaleOrders::class,
            fn (ReleaseStaleOrders $job): bool => $job->tenantId === $this->tenantB,
        );
        Queue::assertNotPushed(
            ReleaseStaleOrders::class,
            fn (ReleaseStaleOrders $job): bool => $job->tenantId === $inactiveTenant,
        );
    }

    private function createTenant(string $name, bool $active): string
    {
        $tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'user_id' => $this->userId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'plan' => 'SEED',
            'is_active' => $active,
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 1,
            'ai_reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tenantId;
    }

    private function createOrder(string $tenantId, mixed $createdAt): int
    {
        return DB::table('orders')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $this->userId,
            'order_number' => 'STALE-'.Str::upper(Str::random(10)),
            'customer_name' => 'Stale Customer',
            'customer_phone' => '9999-9999',
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'source' => 'web',
            'status' => 'pending',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
