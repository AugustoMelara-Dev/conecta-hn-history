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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DovraProgram11PrivacyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_produces_a_dovra_v2_secret_safe_audited_payload_and_is_idempotent(): void
    {
        $user = User::factory()->create([
            'email' => 'portable@example.test',
            'remember_token' => 'must-not-leak',
        ]);
        $tenant = Tenant::factory()->forUser($user)->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();
        $user->createToken('privacy-test');

        $service = $this->service();

        $first = $service->export($user, $user, $tenant, 'export-001');
        $second = $service->export($user, $user, $tenant, 'export-001');

        self::assertSame($first->requestId, $second->requestId);
        self::assertSame('completed', $first->status);
        self::assertSame('dovra-account-export-v2', $first->summary['format']);
        self::assertSame('conecta-hn-account-export-v1', $first->summary['legacy_format']);

        $json = json_encode($first->summary, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('must-not-leak', $json);
        self::assertStringNotContainsString('privacy-test', $json);

        $this->assertDatabaseHas('dovra_privacy_requests', [
            'idempotency_key' => 'export-001',
            'request_type' => 'export',
            'status' => 'completed',
            'subject_id' => (string) $user->id,
        ]);
        $this->assertDatabaseCount('dovra_privacy_request_events', 2);
    }

    public function test_foreign_actor_cannot_request_another_users_export(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $tenant = Tenant::factory()->forUser($subject)->create();

        $this->expectException(AuthorizationException::class);

        $this->service()->export($actor, $subject, $tenant, 'forbidden-export');
    }

    public function test_authorization_precedes_idempotent_replay_for_cross_actor_and_cross_tenant_reuse(): void
    {
        $subject = User::factory()->create();
        $tenantA = Tenant::factory()->forUser($subject)->create();
        $subject->forceFill(['tenant_id' => $tenantA->id])->save();
        $service = $this->service();

        $first = $service->export($subject, $subject, $tenantA, 'shared-privacy-key');

        $foreignActor = User::factory()->create();
        try {
            $service->export($foreignActor, $subject, $tenantA, 'shared-privacy-key');
            self::fail('Cross-actor idempotency replay unexpectedly returned a result.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $crossTenantActor = User::factory()->create();
        $tenantB = Tenant::factory()->forUser($crossTenantActor)->create();
        try {
            $service->export($crossTenantActor, $subject, $tenantB, 'shared-privacy-key');
            self::fail('Cross-tenant idempotency replay unexpectedly returned a result.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        self::assertSame($first->requestId, $service->export($subject, $subject, $tenantA, 'shared-privacy-key')->requestId);
        self::assertSame(1, DB::table('dovra_privacy_requests')->where('idempotency_key', 'shared-privacy-key')->count());
    }

    public function test_anonymize_scrubs_identity_and_revokes_live_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'erase@example.test',
            'phone' => '9999-4444',
            'name' => 'Erase Me',
        ]);
        $user->createToken('privacy-anonymize');
        DB::table('sessions')->insert([
            'id' => 'privacy-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'privacy-suite',
            'payload' => 'opaque',
            'last_activity' => now()->timestamp,
        ]);

        $result = $this->service()->anonymize($user, $user, 'anonymize-001');
        $user->refresh();

        self::assertSame('completed', $result->status);
        self::assertStringEndsWith('@dovra.invalid', (string) $user->email);
        self::assertNull($user->phone);
        self::assertSame('Anonymized User', $user->name);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_legal_hold_requests_are_idempotent_and_block_deletion(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();
        $service = $this->service();

        $first = $service->hold($user, $user, $tenant, 'Regulatory review', 'hold-001');
        $second = $service->hold($user, $user, $tenant, 'Regulatory review', 'hold-001');

        self::assertSame($first->requestId, $second->requestId);
        $this->assertDatabaseHas('dovra_legal_holds', [
            'subject_type' => User::class,
            'subject_id' => (string) $user->id,
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        $service->delete($user, $user, 'delete-blocked-001');
    }

    public function test_delete_routes_through_the_legacy_authority_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $service = $this->service();

        $first = $service->delete($user, $user, 'delete-001');
        $second = $service->delete($user, $user, 'delete-001');

        self::assertSame($first->requestId, $second->requestId);
        self::assertSame('completed', $first->status);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('dovra_privacy_requests', [
            'idempotency_key' => 'delete-001',
            'request_type' => 'delete',
            'status' => 'completed',
        ]);
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
