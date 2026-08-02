<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\AutomationContractService;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ApiWebhookMcpContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_mcp_capabilities_fail_closed_without_a_marketed_provider_contract(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $service = new AutomationContractService(new DatabaseWorkflowRepository(DB::connection()));
        config()->set('automation.providers', []);

        $api = $service->authorizeApi(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'meta',
            capability: 'automation.trigger',
            version: '2026-08-01',
            idempotencyKey: 'api-missing-provider',
            payload: '{"event":"trigger"}',
            signature: 'invalid',
        );

        self::assertFalse($api->allowed);
        self::assertSame('provider_capability_unavailable', $api->code);

        $mcp = $service->authorizeMcp(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'openai-mcp',
            capability: 'automation.mcp.invoke',
            version: '2026-08-01',
            idempotencyKey: 'mcp-missing-provider',
            payload: '{"tool":"lookup"}',
            signature: 'invalid',
        );

        self::assertFalse($mcp->allowed);
        self::assertSame('provider_capability_unavailable', $mcp->code);
    }

    public function test_api_and_mcp_require_signed_tenant_actor_context_and_reject_idempotent_replay(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $service = new AutomationContractService(new DatabaseWorkflowRepository(DB::connection()));
        config()->set('automation.contracts', [
            'api_route_bound' => true,
            'mcp_route_bound' => true,
        ]);
        config()->set('automation.providers', [
            'acme-platform' => [
                'markets' => [
                    'HN' => [
                        'legal_basis' => 'contracted',
                        'capabilities' => ['automation.trigger', 'automation.mcp.invoke'],
                        'api_secret' => 'api-secret',
                        'mcp_secret' => 'mcp-secret',
                    ],
                ],
            ],
        ]);

        $apiPayload = '{"event":"trigger"}';
        $apiKey = 'api-001';
        $apiSignature = $this->signature(
            'api-secret',
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            $apiKey,
            $apiPayload,
        );

        $missingActor = $service->authorizeApi(
            tenantId: (string) $tenant->getKey(),
            actorId: '',
            market: 'HN',
            provider: 'acme-platform',
            capability: 'automation.trigger',
            version: '2026-08-01',
            idempotencyKey: $apiKey,
            payload: $apiPayload,
            signature: $apiSignature,
        );
        self::assertFalse($missingActor->allowed);
        self::assertSame('inbound_context_required', $missingActor->code);

        $api = $service->authorizeApi(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-platform',
            capability: 'automation.trigger',
            version: '2026-08-01',
            idempotencyKey: $apiKey,
            payload: $apiPayload,
            signature: $apiSignature,
        );
        self::assertTrue($api->allowed);
        self::assertSame('accepted', $api->code);

        $apiReplay = $service->authorizeApi(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-platform',
            capability: 'automation.trigger',
            version: '2026-08-01',
            idempotencyKey: $apiKey,
            payload: $apiPayload,
            signature: $apiSignature,
        );
        self::assertFalse($apiReplay->allowed);
        self::assertSame('idempotency_replay', $apiReplay->code);

        $mcpPayload = '{"tool":"lookup"}';
        $mcpKey = 'mcp-001';
        $mcpSignature = $this->signature(
            'mcp-secret',
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            $mcpKey,
            $mcpPayload,
        );
        $mcp = $service->authorizeMcp(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-platform',
            capability: 'automation.mcp.invoke',
            version: '2026-08-01',
            idempotencyKey: $mcpKey,
            payload: $mcpPayload,
            signature: $mcpSignature,
        );
        self::assertTrue($mcp->allowed);
        self::assertSame('accepted', $mcp->code);
    }

    public function test_api_and_mcp_fail_closed_when_no_route_is_bound_even_if_provider_capability_exists(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $service = new AutomationContractService(new DatabaseWorkflowRepository(DB::connection()));
        config()->set('automation.providers', [
            'acme-platform' => [
                'markets' => [
                    'HN' => [
                        'legal_basis' => 'contracted',
                        'capabilities' => ['automation.trigger', 'automation.mcp.invoke'],
                        'api_secret' => 'api-secret',
                        'mcp_secret' => 'mcp-secret',
                    ],
                ],
            ],
        ]);

        $api = $service->authorizeApi(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-platform',
            capability: 'automation.trigger',
            version: '2026-08-01',
            idempotencyKey: 'unbound-api',
            payload: '{}',
            signature: $this->signature('api-secret', (string) $tenant->getKey(), (string) $actor->getKey(), 'unbound-api', '{}'),
        );
        self::assertFalse($api->allowed);
        self::assertSame('route_not_bound', $api->code);

        $mcp = $service->authorizeMcp(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-platform',
            capability: 'automation.mcp.invoke',
            version: '2026-08-01',
            idempotencyKey: 'unbound-mcp',
            payload: '{}',
            signature: $this->signature('mcp-secret', (string) $tenant->getKey(), (string) $actor->getKey(), 'unbound-mcp', '{}'),
        );
        self::assertFalse($mcp->allowed);
        self::assertSame('route_not_bound', $mcp->code);
    }

    public function test_webhook_contract_requires_a_marketed_signature_secret_and_rejects_replay(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $service = new AutomationContractService(new DatabaseWorkflowRepository(DB::connection()));
        $payload = '{"event":"payment.settled"}';

        config()->set('automation.providers', [
            'acme-payments' => [
                'markets' => [
                    'HN' => [
                        'legal_basis' => 'contracted',
                        'capabilities' => ['automation.webhook.receive'],
                        'webhook_secret' => 'top-secret',
                    ],
                ],
            ],
        ]);

        $invalid = $service->verifyWebhook(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-payments',
            capability: 'automation.webhook.receive',
            version: '2026-08-01',
            externalId: 'evt-invalid',
            idempotencyKey: 'webhook-invalid',
            payload: $payload,
            signature: 'bad-signature',
        );

        self::assertFalse($invalid->allowed);
        self::assertSame('invalid_signature', $invalid->code);

        $signature = hash_hmac('sha256', $payload, 'top-secret');
        $signature = $this->signature(
            'top-secret',
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            'webhook-001',
            $payload,
            'evt-001',
        );
        $first = $service->verifyWebhook(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-payments',
            capability: 'automation.webhook.receive',
            version: '2026-08-01',
            externalId: 'evt-001',
            idempotencyKey: 'webhook-001',
            payload: $payload,
            signature: $signature,
        );

        self::assertTrue($first->allowed);
        self::assertSame('accepted', $first->code);
        $this->assertDatabaseHas('automation_webhook_deliveries', [
            'tenant_id' => $tenant->getKey(),
            'actor_id' => $actor->getKey(),
            'provider' => 'acme-payments',
            'external_id' => 'evt-001',
        ]);

        $replay = $service->verifyWebhook(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            market: 'HN',
            provider: 'acme-payments',
            capability: 'automation.webhook.receive',
            version: '2026-08-01',
            externalId: 'evt-001',
            idempotencyKey: 'webhook-001',
            payload: $payload,
            signature: $signature,
        );

        self::assertFalse($replay->allowed);
        self::assertSame('webhook_replay', $replay->code);
    }

    private function signature(
        string $secret,
        string $tenantId,
        string $actorId,
        string $idempotencyKey,
        string $payload,
        ?string $externalId = null,
    ): string {
        $canonical = implode('|', array_filter([
            $tenantId,
            $actorId,
            $externalId,
            $idempotencyKey,
            $payload,
        ], static fn (?string $value): bool => $value !== null));

        return hash_hmac('sha256', $canonical, $secret);
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
}
