<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Domain\Common\Contracts\SnapshotStrategyInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Infrastructure\Identity\Repositories\EventSourcedUserRepository;
use App\Infrastructure\Tenancy\Repositories\EventSourcedTenantRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SnapshotAggregateTypePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(SnapshotStrategyInterface::class, static fn () => new class implements SnapshotStrategyInterface
        {
            public function shouldSnapshot(object $aggregate): bool
            {
                return true;
            }
        });
    }

    #[Test]
    public function it_persists_tenant_snapshots_with_the_tenant_aggregate_type(): void
    {
        $tenant = TenantEntity::create(
            id: Str::uuid()->toString(),
            name: 'Snapshot Tenant',
            domain: 'snapshot-tenant'
        );

        app(EventSourcedTenantRepository::class)->save($tenant);

        $this->assertDatabaseHas('snapshots', [
            'aggregate_uuid' => $tenant->id(),
            'aggregate_type' => 'Tenant',
        ]);
    }

    #[Test]
    public function it_persists_user_snapshots_with_the_user_aggregate_type(): void
    {
        $tenantId = Str::uuid()->toString();
        $tenant = TenantEntity::create(
            id: $tenantId,
            name: 'Snapshot User Tenant',
            domain: 'snapshot-user-tenant'
        );

        app(EventSourcedTenantRepository::class)->save($tenant);

        $user = UserEntity::register(
            uuid: Str::uuid()->toString(),
            name: 'Snapshot User',
            email: 'snapshot-user@example.com',
            passwordHash: bcrypt('secret'),
            role: UserRole::CUSTOMER,
            tenantId: $tenantId
        );

        app(EventSourcedUserRepository::class)->save($user);

        $this->assertDatabaseHas('snapshots', [
            'aggregate_uuid' => $user->id(),
            'aggregate_type' => 'User',
        ]);

        $record = DB::table('snapshots')
            ->where('aggregate_uuid', $user->id())
            ->first();

        self::assertNotNull($record);
    }
}
