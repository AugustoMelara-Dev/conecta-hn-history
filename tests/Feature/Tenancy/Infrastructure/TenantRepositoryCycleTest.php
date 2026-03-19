<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\Infrastructure;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Tenancy\Repositories\EventSourcedTenantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRepositoryCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(TenantRepositoryInterface::class, EventSourcedTenantRepository::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_save_and_rehydrate_a_tenant()
    {
        // 1. Arrange (Write)
        $id = Str::uuid()->toString();
        $name = 'Phoenix Industries';
        $domain = 'phoenix-labs';

        $tenant = TenantEntity::create($id, $name, $domain);

        /** @var TenantRepositoryInterface $repo */
        $repo = $this->app->make(TenantRepositoryInterface::class);

        // 2. Act (Persist)
        $repo->save($tenant);

        // 3. Act (Read)
        // Force clear memory or instantiate new repo logic if caching exists (Repository is stateless mostly)
        $retrievedTenant = $repo->findById($id);

        // 4. Assert
        $this->assertNotNull($retrievedTenant, 'Tenant should be retrieved from Event Store.');
        $this->assertInstanceOf(TenantEntity::class, $retrievedTenant);
        $this->assertEquals($id, $retrievedTenant->id());
        $this->assertEquals($name, $retrievedTenant->name());
        $this->assertEquals($domain, $retrievedTenant->domain());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_non_existent_tenant()
    {
        $repo = $this->app->make(TenantRepositoryInterface::class);
        $result = $repo->findById(Str::uuid()->toString());

        $this->assertNull($result);
    }
}
