<?php

namespace Tests\Feature\Billing;

use App\Application\Billing\Jobs\ReconcilePendingSubscriptionsJob;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ReconcilePendingSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private ConnectionInterface $mockConnection;
    private Builder $mockBuilder;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant for FK constraints
        DB::table('tenants')->insert([
            'id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-slug',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock the ConnectionInterface and Query Builder chain
        $this->mockBuilder = Mockery::mock(Builder::class);
        $this->mockConnection = Mockery::mock(ConnectionInterface::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
    }

    /** @test */
    public function it_ignores_recent_pending_subscriptions()
    {
        // ARRANGE — No stale intents exist
        $this->mockConnection
            ->shouldReceive('table')
            ->with('subscription_intents')
            ->once()
            ->andReturn($this->mockBuilder);

        $this->mockBuilder->shouldReceive('where')->andReturnSelf();
        $this->mockBuilder->shouldReceive('get')->once()->andReturn(collect([]));

        // Mock Gateway to ensure it is NOT called
        $mockGateway = Mockery::mock(SubscriptionGatewayInterface::class);
        $mockGateway->shouldReceive('verifyStatus')->never();

        // ACT
        $job = new ReconcilePendingSubscriptionsJob;
        $job->handle($mockGateway, $this->mockLogger, $this->mockConnection);

        // ASSERT — If gateway was called, Mockery would fail (->never())
        expect(true)->toBeTrue();
    }

    /** @test */
    public function it_reconciles_old_pending_subscriptions_to_paid()
    {
        // ARRANGE — One stale intent exists
        $staleIntent = (object) [
            'id' => '00000000-0000-0000-0000-000000000200',
            'tenant_id' => '00000000-0000-0000-0000-000000000001',
            'plan_identifier' => 'pro_monthly',
            'gateway_provider' => 'stripe',
            'status' => 'PENDING',
            'client_idempotency_key' => 'key_stuck',
            'created_at' => '2020-01-01 00:00:00',
        ];

        // SELECT query: table -> where(status) -> where(created_at) -> get()
        $selectBuilder = Mockery::mock(Builder::class);
        $selectBuilder->shouldReceive('where')->andReturnSelf();
        $selectBuilder->shouldReceive('get')->once()->andReturn(collect([$staleIntent]));

        // UPDATE query: table -> where(id) -> update(...)
        $updateBuilder = Mockery::mock(Builder::class);
        $updateBuilder->shouldReceive('where')->with('id', $staleIntent->id)->once()->andReturnSelf();
        $updateBuilder->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['status'] === 'COMPLETED'
                && $data['notes'] === 'Auto-reconciled by ReconcilePendingSubscriptionsJob';
        }))->andReturn(1);

        // Wire connection: first table() call = SELECT, second = UPDATE
        $this->mockConnection
            ->shouldReceive('table')
            ->with('subscription_intents')
            ->twice()
            ->andReturn($selectBuilder, $updateBuilder);

        // Mock Gateway returns PAID
        $mockGateway = Mockery::mock(SubscriptionGatewayInterface::class);
        $mockGateway->shouldReceive('verifyStatus')
            ->with('key_stuck')
            ->once()
            ->andReturn('PAID');

        // ACT
        $job = new ReconcilePendingSubscriptionsJob;
        $job->handle($mockGateway, $this->mockLogger, $this->mockConnection);

        // ASSERT — Mockery expectations verify the update was called with COMPLETED
    }

    /** @test */
    public function it_reconciles_old_pending_subscriptions_to_failed()
    {
        // ARRANGE — One stale intent exists
        $staleIntent = (object) [
            'id' => '00000000-0000-0000-0000-000000000300',
            'tenant_id' => '00000000-0000-0000-0000-000000000001',
            'plan_identifier' => 'pro_monthly',
            'gateway_provider' => 'stripe',
            'status' => 'PENDING',
            'client_idempotency_key' => 'key_fail',
            'created_at' => '2020-01-01 00:00:00',
        ];

        // SELECT query
        $selectBuilder = Mockery::mock(Builder::class);
        $selectBuilder->shouldReceive('where')->andReturnSelf();
        $selectBuilder->shouldReceive('get')->once()->andReturn(collect([$staleIntent]));

        // UPDATE query
        $updateBuilder = Mockery::mock(Builder::class);
        $updateBuilder->shouldReceive('where')->with('id', $staleIntent->id)->once()->andReturnSelf();
        $updateBuilder->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['status'] === 'FAILED'
                && $data['notes'] === 'Auto-reconciled by ReconcilePendingSubscriptionsJob';
        }))->andReturn(1);

        // Wire connection
        $this->mockConnection
            ->shouldReceive('table')
            ->with('subscription_intents')
            ->twice()
            ->andReturn($selectBuilder, $updateBuilder);

        // Mock Gateway returns FAILED
        $mockGateway = Mockery::mock(SubscriptionGatewayInterface::class);
        $mockGateway->shouldReceive('verifyStatus')
            ->with('key_fail')
            ->once()
            ->andReturn('FAILED');

        // ACT
        $job = new ReconcilePendingSubscriptionsJob;
        $job->handle($mockGateway, $this->mockLogger, $this->mockConnection);

        // ASSERT — Mockery expectations verify the update was called with FAILED
    }
}
