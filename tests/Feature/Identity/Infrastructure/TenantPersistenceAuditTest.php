<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Infrastructure;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Events\TenantCreated;
use App\Infrastructure\Tenancy\Repositories\EventSourcedTenantRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantPersistenceAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Bind mocks or real implementations if not strictly bound
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_persists_tenant_created_event_to_store_and_outbox()
    {
        // 1. Setup
        $id = Str::uuid()->toString();
        $name = 'Titanium Corp';
        $domain = 'titanium-corp';

        $tenant = TenantEntity::create($id, $name, $domain);

        // 2. Execution
        // Resolve real repository to test the plumbing
        $repo = app(EventSourcedTenantRepository::class);
        $repo->save($tenant);

        // 3. Audit Event Store
        $this->assertDatabaseHas('stored_events', [
            'aggregate_uuid' => $id,
            'aggregate_type' => 'Tenant',
            'event_type' => TenantCreated::class,
        ]);

        // 4. Audit Outbox
        $this->assertDatabaseHas('outbox_messages', [
            'type' => 'Identity.TenantCreated.v1',
        ]);

        // Verify Payload content in Outbox
        $outboxEntry = \DB::table('outbox_messages')->where('type', 'Identity.TenantCreated.v1')->first();
        $this->assertNotNull($outboxEntry);

        $payload = json_decode($outboxEntry->payload, true);
        $this->assertEquals($id, $payload['tenant_id']);
        $this->assertEquals($name, $payload['name']);
        $this->assertEquals($domain, $payload['domain']);
    }
}
