<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BackfillWorkforceRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_registry_backfill_command_writes_stable_workforce_role_metadata(): void
    {
        $platformTenant = Tenant::factory()->create();
        config()->set('ai.public.billing_tenant_id', (string) $platformTenant->getKey());
        config()->set('ai.private.enabled', false);
        config()->set('ai.providers', [
            'openai' => [
                'enabled' => true,
                'adapter_key' => 'openai_structured_text',
                'secret_reference' => 'openai.primary',
                'models' => [
                    'gpt-4o-mini' => [
                        'input_usd_per_million' => 0.15,
                        'output_usd_per_million' => 0.60,
                    ],
                ],
            ],
        ]);
        config()->set('ai.capabilities', [
            'public_discovery' => [
                'enabled' => true,
                'provider' => 'openai',
                'models' => ['gpt-4o-mini'],
                'tools' => ['public_catalog.search'],
            ],
        ]);

        $this->artisan('dovra:ai-registry-backfill', ['--json' => true])
            ->expectsOutputToContain('"agentVersions"')
            ->assertSuccessful();
        $this->artisan('dovra:ai-registry-backfill', ['--json' => true])->assertSuccessful();

        self::assertSame(1, DB::table('ai_agent_versions')->count());
        $configuration = json_decode(
            (string) DB::table('ai_agent_versions')->value('configuration'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('front_desk', $configuration['workforce_role'] ?? null);
    }
}
