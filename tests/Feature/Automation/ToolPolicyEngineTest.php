<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Domain\Automation\ToolAuthorizationRequest;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Application\Automation\ToolPolicyEngine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ToolPolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutating_tools_are_denied_by_default(): void
    {
        [$tenant, $actor] = $this->tenantActor();

        $decision = $this->engine()->authorize(new ToolAuthorizationRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            tool: 'orders.capture',
            toolVersion: '2026-08-01',
            risk: 'mutation',
            payloadHash: $this->payloadHash(['order_id' => 'ord-1']),
            idempotencyKey: 'tool-default-deny',
            confirmationId: null,
            confirmationToken: null,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame('policy_missing', $decision->code);
    }

    #[DataProvider('policyDenialCases')]
    public function test_policy_enforces_role_plan_channel_and_risk(
        array $roles,
        string $plan,
        string $channel,
        string $risk,
        string $expectedCode,
    ): void {
        [$tenant, $actor] = $this->tenantActor();

        DB::table('automation_tool_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'tool' => 'reports.generate',
            'tool_version' => '2026-08-01',
            'allowed_roles' => json_encode(['manager'], JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['scale'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode(['mcp'], JSON_THROW_ON_ERROR),
            'max_risk' => 'prepare',
            'confirmation_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $decision = $this->engine()->authorize(new ToolAuthorizationRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: $roles,
            plan: $plan,
            channel: $channel,
            tool: 'reports.generate',
            toolVersion: '2026-08-01',
            risk: $risk,
            payloadHash: $this->payloadHash(['report' => 'daily']),
            idempotencyKey: "policy-{$expectedCode}",
            confirmationId: null,
            confirmationToken: null,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame($expectedCode, $decision->code);
    }

    public static function policyDenialCases(): array
    {
        return [
            'role denied' => [['owner'], 'scale', 'mcp', 'prepare', 'role_denied'],
            'plan denied' => [['manager'], 'pro', 'mcp', 'prepare', 'plan_denied'],
            'channel denied' => [['manager'], 'scale', 'api', 'prepare', 'channel_denied'],
            'risk denied' => [['manager'], 'scale', 'mcp', 'mutation', 'risk_denied'],
        ];
    }

    public function test_confirmation_must_match_tenant_actor_payload_and_tool_version(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        [$foreignTenant, $foreignActor] = $this->tenantActor();
        $payloadHash = $this->payloadHash(['lead_id' => 'lead-1']);
        $confirmationId = (string) Str::uuid();

        DB::table('automation_tool_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'tool' => 'leads.promote',
            'tool_version' => '2026-08-01',
            'allowed_roles' => json_encode(['owner'], JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['pro'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode(['api'], JSON_THROW_ON_ERROR),
            'max_risk' => 'economic',
            'confirmation_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('automation_confirmation_attempts')->insert([
            'id' => $confirmationId,
            'tenant_id' => $foreignTenant->getKey(),
            'actor_id' => $foreignActor->getKey(),
            'tool' => 'leads.promote',
            'tool_version' => '2026-08-01',
            'payload_hash' => $payloadHash,
            'confirmation_token_hash' => hash('sha256', 'token-123'),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'encrypted_payload' => encrypt(['lead_id' => 'lead-1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $decision = $this->engine()->authorize(new ToolAuthorizationRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            tool: 'leads.promote',
            toolVersion: '2026-08-01',
            risk: 'economic',
            payloadHash: $payloadHash,
            idempotencyKey: 'confirmation-mismatch',
            confirmationId: $confirmationId,
            confirmationToken: 'token-123',
        ));

        self::assertFalse($decision->allowed);
        self::assertSame('confirmation_binding_mismatch', $decision->code);
    }

    public function test_confirmation_expiry_and_idempotency_replay_fail_closed(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $payloadHash = $this->payloadHash(['order_id' => 'ord-1']);

        DB::table('automation_tool_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'tool' => 'orders.capture',
            'tool_version' => '2026-08-01',
            'allowed_roles' => json_encode(['owner'], JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['pro'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode(['api'], JSON_THROW_ON_ERROR),
            'max_risk' => 'economic',
            'confirmation_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expiredConfirmationId = (string) Str::uuid();
        DB::table('automation_confirmation_attempts')->insert([
            'id' => $expiredConfirmationId,
            'tenant_id' => $tenant->getKey(),
            'actor_id' => $actor->getKey(),
            'tool' => 'orders.capture',
            'tool_version' => '2026-08-01',
            'payload_hash' => $payloadHash,
            'confirmation_token_hash' => hash('sha256', 'expired-token'),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
            'encrypted_payload' => encrypt(['order_id' => 'ord-1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expired = $this->engine()->authorize(new ToolAuthorizationRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            tool: 'orders.capture',
            toolVersion: '2026-08-01',
            risk: 'economic',
            payloadHash: $payloadHash,
            idempotencyKey: 'orders-capture-expired',
            confirmationId: $expiredConfirmationId,
            confirmationToken: 'expired-token',
        ));

        self::assertFalse($expired->allowed);
        self::assertSame('confirmation_expired', $expired->code);

        $freshConfirmationId = (string) Str::uuid();
        DB::table('automation_confirmation_attempts')->insert([
            'id' => $freshConfirmationId,
            'tenant_id' => $tenant->getKey(),
            'actor_id' => $actor->getKey(),
            'tool' => 'orders.capture',
            'tool_version' => '2026-08-01',
            'payload_hash' => $payloadHash,
            'confirmation_token_hash' => hash('sha256', 'fresh-token'),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'encrypted_payload' => encrypt(['order_id' => 'ord-1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = new ToolAuthorizationRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            tool: 'orders.capture',
            toolVersion: '2026-08-01',
            risk: 'economic',
            payloadHash: $payloadHash,
            idempotencyKey: 'orders-capture-replay',
            confirmationId: $freshConfirmationId,
            confirmationToken: 'fresh-token',
        );

        $first = $this->engine()->authorize($request);
        self::assertTrue($first->allowed);
        self::assertSame('authorized', $first->code);
        $this->assertDatabaseHas('automation_confirmation_attempts', [
            'id' => $freshConfirmationId,
            'status' => 'consumed',
        ]);
        $this->assertDatabaseHas('automation_integration_idempotency', [
            'tenant_id' => $tenant->getKey(),
            'scope' => 'tool:local:orders.capture',
            'idempotency_key' => 'orders-capture-replay',
        ]);

        $second = $this->engine()->authorize($request);
        self::assertFalse($second->allowed);
        self::assertSame('idempotency_replay', $second->code);
    }

    private function engine(): ToolPolicyEngine
    {
        return new ToolPolicyEngine(new DatabaseWorkflowRepository(DB::connection()));
    }

    /** @return array{Tenant, User} */
    private function tenantActor(): array
    {
        /** @var array{Tenant, User} $result */
        $result = Model::withoutEvents(function (): array {
            $actor = User::factory()->create();
            $tenant = Tenant::factory()->forUser($actor)->pro()->create();
            $actor->forceFill(['tenant_id' => $tenant->getKey()])->save();

            return [$tenant, $actor];
        });

        return $result;
    }

    /** @param  array<string, mixed>  $payload */
    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
