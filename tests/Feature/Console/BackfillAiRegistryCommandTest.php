<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackfillAiRegistryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_the_registry_backfill_and_emits_a_machine_readable_report(): void
    {
        $platformTenant = Tenant::factory()->create();
        config()->set('ai.public.billing_tenant_id', (string) $platformTenant->getKey());
        config()->set('ai.providers.openai.enabled', true);
        config()->set('ai.providers.openai.adapter_key', 'openai_structured_text');
        config()->set('ai.providers.openai.secret_reference', 'openai.primary');
        config()->set('ai.capabilities.public_discovery.enabled', true);

        $this->artisan('dovra:ai-registry-backfill', ['--json' => true])
            ->expectsOutputToContain('"providers"')
            ->assertSuccessful();

        self::assertDatabaseHas('ai_providers', ['provider_key' => 'openai']);
    }
}
