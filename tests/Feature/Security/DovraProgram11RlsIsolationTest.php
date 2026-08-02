<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Infrastructure\Privacy\Dovra\DatabasePrivacyEvidenceStore;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DovraProgram11RlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_request_idempotency_index_is_composite_and_not_global(): void
    {
        $indexes = collect(Schema::getIndexes('dovra_privacy_requests'));

        self::assertTrue($indexes->contains(static fn (array $index): bool => ($index['name'] ?? null) === 'dovra_privacy_requests_tenant_actor_subject_action_idempotency_unique'
            && ($index['unique'] ?? false) === true
            && ($index['columns'] ?? []) === [
                'tenant_id',
                'actor_id',
                'subject_type',
                'subject_id',
                'request_type',
                'idempotency_key',
            ]));
        self::assertFalse($indexes->contains(static fn (array $index): bool => ($index['name'] ?? null) === 'dovra_privacy_requests_idempotency_key_unique'));
    }

    public function test_evidence_store_never_returns_a_foreign_tenant_or_actor_request_for_a_reused_key(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $actorA = User::factory()->create();
        $actorB = User::factory()->create();
        $subjectA = User::factory()->create();
        $subjectB = User::factory()->create();
        $store = new DatabasePrivacyEvidenceStore;

        $requestA = $store->beginRequest('export', 'shared-key', (string) $tenantA->id, (string) $actorA->id, User::class, (string) $subjectA->id);
        $requestB = $store->beginRequest('export', 'shared-key', (string) $tenantB->id, (string) $actorB->id, User::class, (string) $subjectB->id);

        self::assertSame($requestA, $store->findRequestByKey('shared-key', (string) $tenantA->id, (string) $actorA->id, User::class, (string) $subjectA->id, 'export')['id']);
        self::assertSame($requestB, $store->findRequestByKey('shared-key', (string) $tenantB->id, (string) $actorB->id, User::class, (string) $subjectB->id, 'export')['id']);
        self::assertNull($store->findRequestByKey('shared-key', (string) $tenantA->id, (string) $actorB->id, User::class, (string) $subjectA->id, 'export'));
        self::assertNull($store->findRequestByKey('shared-key', (string) $tenantB->id, (string) $actorA->id, User::class, (string) $subjectB->id, 'export'));
        self::assertSame(2, DB::table('dovra_privacy_requests')->where('idempotency_key', 'shared-key')->count());
    }
}
