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
        [$tenant] = $this->tenantActor();
        $service = new AutomationContractService(new DatabaseWorkflowRepository(DB::connection()));
        config()->set('automation.providers', []);

        $api = $service->authorizeApi(
            tenantId: (string) $tenant->getKey(),
            market: 'HN',
            provider: 'meta',
            capability: 'automation.trigger',
            version: '2026-08-01',
        );

        self::assertFalse($api->allowed);
        self::assertSame('provider_capability_unavailable', $api->code);

        $mcp = $service->authorizeMcp(
            tenantId: (string) $tenant->getKey(),
            market: 'HN',
            provider: 'openai-mcp',
            capability: 'automation.mcp.invoke',
            version: '2026-08-01',
        );

        self::assertFalse($mcp->allowed);
        self::assertSame('provider_capability_unavailable', $mcp->code);
    }

    public function test_webhook_contract_requires_a_marketed_signature_secret_and_rejects_replay(): void
    {
        [$tenant] = $this->tenantActor();
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
            market: 'HN',
            provider: 'acme-payments',
            capability: 'automation.webhook.receive',
            version: '2026-08-01',
            externalId: 'evt-invalid',
            payload: $payload,
            signature: 'bad-signature',
        );

        self::assertFalse($invalid->allowed);
        self::assertSame('invalid_signature', $invalid->code);

        $signature = hash_hmac('sha256', $payload, 'top-secret');
        $first = $service->verifyWebhook(
            tenantId: (string) $tenant->getKey(),
            market: 'HN',
            provider: 'acme-payments',
            capability: 'automation.webhook.receive',
            version: '2026-08-01',
            externalId: 'evt-001',
            payload: $payload,
            signature: $signature,
        );

        self::assertTrue($first->allowed);
        self::assertSame('accepted', $first->code);
        $this->assertDatabaseHas('automation_webhook_deliveries', [
            'tenant_id' => $tenant->getKey(),
            'provider' => 'acme-payments',
            'external_id' => 'evt-001',
        ]);

        $replay = $service->verifyWebhook(
            tenantId: (string) $tenant->getKey(),
            market: 'HN',
            provider: 'acme-payments',
            capability: 'automation.webhook.receive',
            version: '2026-08-01',
            externalId: 'evt-001',
            payload: $payload,
            signature: $signature,
        );

        self::assertFalse($replay->allowed);
        self::assertSame('webhook_replay', $replay->code);
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
