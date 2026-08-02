<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessGraph;

use App\Application\BusinessGraph\BusinessGraphEdge;
use App\Application\BusinessGraph\BusinessGraphFact;
use App\Infrastructure\BusinessGraph\DatabaseBusinessGraphRepository;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class BusinessGraphRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_only_current_non_revoked_rows_for_the_requested_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $repository = new DatabaseBusinessGraphRepository;

        $repository->upsertFact(BusinessGraphFact::fromSource(
            tenantId: (string) $tenantA->getKey(),
            subjectType: 'tenant',
            subjectId: (string) $tenantA->getKey(),
            factKey: 'business.name',
            value: ['value' => 'Tenant A'],
            sourceType: 'tenant',
            sourceId: 'tenant:'.$tenantA->getKey(),
            confidence: 1.0,
        ));
        $repository->upsertFact(BusinessGraphFact::fromSource(
            tenantId: (string) $tenantB->getKey(),
            subjectType: 'tenant',
            subjectId: (string) $tenantB->getKey(),
            factKey: 'business.name',
            value: ['value' => 'Tenant B'],
            sourceType: 'tenant',
            sourceId: 'tenant:'.$tenantB->getKey(),
            confidence: 1.0,
        ));
        $repository->upsertEdge(BusinessGraphEdge::fromSource(
            tenantId: (string) $tenantA->getKey(),
            fromType: 'tenant',
            fromId: (string) $tenantA->getKey(),
            relation: 'owns',
            toType: 'product',
            toId: 'product-a',
            sourceType: 'product',
            sourceId: 'product:product-a',
        ));
        $repository->upsertEdge(BusinessGraphEdge::fromSource(
            tenantId: (string) $tenantB->getKey(),
            fromType: 'tenant',
            fromId: (string) $tenantB->getKey(),
            relation: 'owns',
            toType: 'product',
            toId: 'product-b',
            sourceType: 'product',
            sourceId: 'product:product-b',
        ));
        $repository->upsertFact(BusinessGraphFact::fromSource(
            tenantId: (string) $tenantA->getKey(),
            subjectType: 'tenant',
            subjectId: (string) $tenantA->getKey(),
            factKey: 'business.revoked',
            value: ['value' => 'revoked'],
            sourceType: 'tenant',
            sourceId: 'tenant:revoked',
            confidence: 1.0,
        ));
        $repository->upsertEdge(BusinessGraphEdge::fromSource(
            tenantId: (string) $tenantA->getKey(),
            fromType: 'tenant',
            fromId: (string) $tenantA->getKey(),
            relation: 'expired',
            toType: 'product',
            toId: 'product-expired',
            sourceType: 'product',
            sourceId: 'product:expired',
        ));
        DB::table('dovra_business_facts')
            ->where('tenant_id', (string) $tenantA->getKey())
            ->where('fact_key', 'business.revoked')
            ->update(['revoked_at' => now()]);
        DB::table('dovra_business_edges')
            ->where('tenant_id', (string) $tenantA->getKey())
            ->where('relation', 'expired')
            ->update(['valid_until' => now()->subMinute()]);

        $snapshot = $repository->snapshot((string) $tenantA->getKey());

        $this->assertSame((string) $tenantA->getKey(), $snapshot->tenantId);
        $this->assertCount(1, $snapshot->facts);
        $this->assertCount(1, $snapshot->edges);
        $this->assertSame('Tenant A', $snapshot->facts[0]->value['value']);
        $this->assertSame('product-a', $snapshot->edges[0]->toId);
    }

    public function test_fact_and_edge_upserts_are_idempotent_by_tenant_and_source_identity(): void
    {
        $tenant = Tenant::factory()->create();
        $repository = new DatabaseBusinessGraphRepository;

        $fact = BusinessGraphFact::fromSource(
            tenantId: (string) $tenant->getKey(),
            subjectType: 'product',
            subjectId: '42',
            factKey: 'catalog.name',
            value: ['value' => 'Original name'],
            sourceType: 'product',
            sourceId: 'product:42',
            confidence: 0.95,
        );
        $edge = BusinessGraphEdge::fromSource(
            tenantId: (string) $tenant->getKey(),
            fromType: 'tenant',
            fromId: (string) $tenant->getKey(),
            relation: 'owns',
            toType: 'product',
            toId: '42',
            sourceType: 'product',
            sourceId: 'product:42',
        );

        $repository->upsertFact($fact);
        $repository->upsertFact($fact);
        $repository->upsertEdge($edge);
        $repository->upsertEdge($edge);

        $snapshot = $repository->snapshot((string) $tenant->getKey());

        $this->assertCount(1, $snapshot->facts);
        $this->assertCount(1, $snapshot->edges);
    }

    public function test_fact_value_object_rejects_empty_identity_parts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BusinessGraphFact::fromSource(
            tenantId: ' ',
            subjectType: 'tenant',
            subjectId: 'tenant-1',
            factKey: 'business.name',
            value: ['value' => 'Tenant'],
            sourceType: 'tenant',
            sourceId: 'tenant:tenant-1',
            confidence: 1.0,
        );
    }

    public function test_fact_value_object_rejects_invalid_confidence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BusinessGraphFact::fromSource(
            tenantId: 'tenant-1',
            subjectType: 'tenant',
            subjectId: 'tenant-1',
            factKey: 'business.name',
            value: ['value' => 'Tenant'],
            sourceType: 'tenant',
            sourceId: 'tenant:tenant-1',
            confidence: 1.1,
        );
    }

    public function test_edge_value_object_rejects_empty_relations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BusinessGraphEdge::fromSource(
            tenantId: 'tenant-1',
            fromType: 'tenant',
            fromId: 'tenant-1',
            relation: '',
            toType: 'product',
            toId: 'product-1',
            sourceType: 'product',
            sourceId: 'product:product-1',
        );
    }
}
