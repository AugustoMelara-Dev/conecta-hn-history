<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Application\Privacy\AccountPrivacyService;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\PrivacyRequestService;
use App\Application\Privacy\Dovra\RetentionLifecycleService;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DatabasePrivacyEvidenceStore;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\LegacyAccountPrivacyAdapter;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DovraProgram11RetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_disposes_only_expired_records_without_an_active_legal_hold(): void
    {
        $tenant = Tenant::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userB->forceFill(['tenant_id' => $tenant->id])->save();

        $store = new DatabasePrivacyEvidenceStore;
        $retention = new RetentionLifecycleService($store);

        $retention->registerRecord(
            tenantId: (string) $tenant->id,
            subjectType: User::class,
            subjectId: (string) $userA->id,
            classification: 'ordinary_personal',
            retentionCategory: 'conversation_standard',
            sourceSystem: 'conversation_store',
            sourceReference: 'conv-a',
            createdAt: now()->subDays(120),
        );

        $retention->registerRecord(
            tenantId: (string) $tenant->id,
            subjectType: User::class,
            subjectId: (string) $userB->id,
            classification: 'ordinary_personal',
            retentionCategory: 'conversation_standard',
            sourceSystem: 'conversation_store',
            sourceReference: 'conv-b',
            createdAt: now()->subDays(120),
        );

        $this->service()->hold($userB, $userB, $tenant, 'Litigation hold', 'hold-retention-001');

        $result = $retention->disposeExpired();

        self::assertSame(1, $result['disposed']);
        self::assertSame(1, $result['held']);
        $this->assertDatabaseHas('dovra_data_retention_records', [
            'subject_id' => (string) $userA->id,
            'source_reference' => 'conv-a',
            'disposition_status' => 'disposed',
        ]);
        $this->assertDatabaseHas('dovra_data_retention_records', [
            'subject_id' => (string) $userB->id,
            'source_reference' => 'conv-b',
            'disposition_status' => 'pending',
        ]);
    }

    public function test_retention_registration_fails_closed_for_unknown_categories(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RetentionLifecycleService(new DatabasePrivacyEvidenceStore))->registerRecord(
            tenantId: null,
            subjectType: 'conversation',
            subjectId: 'conv-001',
            classification: 'ordinary_personal',
            retentionCategory: 'unknown_category',
            sourceSystem: 'conversation_store',
            sourceReference: 'conv-001',
        );
    }

    private function service(): PrivacyRequestService
    {
        $store = new DatabasePrivacyEvidenceStore;
        $policy = new DataClassificationPolicy(
            new LegacySensitivePayloadRedactor(new SensitiveDataScrubber),
        );
        $retention = new RetentionLifecycleService($store);

        return new PrivacyRequestService(
            new LegacyAccountPrivacyAdapter(new AccountPrivacyService),
            $policy,
            $store,
            new DovraIntegrationSeamRegistry,
            $retention,
        );
    }
}
