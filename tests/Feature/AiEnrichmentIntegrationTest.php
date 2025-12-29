<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\EnhanceTenantProfileJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integration Tests for AI Enrichment Pipeline
 *
 * These tests use HTTP::fake() for full end-to-end testing without
 * Mockery state pollution issues encountered in Pest tests.
 */
class AiEnrichmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-api-key']);

        $this->user = User::factory()->create(['role' => 'seller']);
    }

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::forceCreate(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'is_active' => true,
            'is_ai_enhanced' => false,
            'subscription_status' => 'active',
            'plan_type' => 'pro',
        ], $overrides));
    }

    private function fakeOpenAiResponse(string $status = 'success', array $overrides = []): void
    {
        $defaultResponse = [
            'status' => $status,
            'business_name_clean' => 'Pulpería Don Mario',
            'tagline' => 'Tu tienda de confianza',
            'short_description' => 'La mejor pulpería de Tegucigalpa',
            'full_description_html' => '<p>Bienvenidos a nuestra pulpería.</p>',
            'seo_keywords' => ['pulpería', 'tegucigalpa', 'tienda'],
            'risk_analysis' => $status === 'flagged'
                ? 'Detected references to prohibited substances'
                : 'Low risk - standard retail business',
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(array_merge($defaultResponse, $overrides)),
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JOB INTEGRATION TESTS
    // ═══════════════════════════════════════════════════════════════════════

    public function test_job_enriches_tenant_with_real_service(): void
    {
        $this->fakeOpenAiResponse('success');

        $tenant = $this->createTenant([
            'name' => 'pulperia don mario',
        ]);

        $job = new EnhanceTenantProfileJob($tenant->id);
        $job->handle(app(\App\Services\AiProfileGeneratorService::class));

        $tenant->refresh();

        $this->assertEquals('Pulpería Don Mario', $tenant->name);
        $this->assertEquals('Tu tienda de confianza', $tenant->slogan);
        $this->assertTrue($tenant->is_ai_enhanced);
    }

    public function test_job_bans_tenant_when_flagged(): void
    {
        $this->fakeOpenAiResponse('flagged');

        $tenant = $this->createTenant([
            'name' => 'Suspicious Business',
        ]);

        $job = new EnhanceTenantProfileJob($tenant->id);
        $job->handle(app(\App\Services\AiProfileGeneratorService::class));

        $tenant->refresh();

        $this->assertEquals('banned', $tenant->status);
        $this->assertFalse($tenant->is_active);
        $this->assertStringContainsString('AI MODERATION', $tenant->admin_notes);
    }

    public function test_job_skips_nonexistent_tenant(): void
    {
        $job = new EnhanceTenantProfileJob(999999);
        $job->handle(app(\App\Services\AiProfileGeneratorService::class));

        // No exception thrown = test passes
        $this->assertTrue(true);
    }

    public function test_job_skips_already_enriched_tenant(): void
    {
        // Fake HTTP to prevent real API calls (just in case)
        Http::fake();

        $tenant = $this->createTenant([
            'is_ai_enhanced' => true,
            'name' => 'Already Enriched Tenant',
        ]);

        $originalName = $tenant->name;

        $job = new EnhanceTenantProfileJob($tenant->id);
        $job->handle(app(\App\Services\AiProfileGeneratorService::class));

        $tenant->refresh();

        // Name should NOT have changed - job should have skipped
        $this->assertEquals($originalName, $tenant->name);
        $this->assertTrue($tenant->is_ai_enhanced);

        // Verify NO HTTP calls were made
        Http::assertNothingSent();
    }

    public function test_job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();

        EnhanceTenantProfileJob::dispatch($tenant->id);

        Queue::assertPushed(EnhanceTenantProfileJob::class, function ($job) use ($tenant) {
            return $job->uniqueId() === 'enhance-tenant-' . $tenant->id;
        });
    }

    public function test_job_has_correct_configuration(): void
    {
        $job = new EnhanceTenantProfileJob(1);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
        $this->assertEquals(3600, $job->uniqueFor);
        $this->assertEquals('ai-enrichment', $job->queue);
    }

    public function test_job_rethrows_exception_on_api_failure(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'API Error'],
            ], 500),
        ]);

        $tenant = $this->createTenant();

        $this->expectException(\Exception::class);

        $job = new EnhanceTenantProfileJob($tenant->id);
        $job->handle(app(\App\Services\AiProfileGeneratorService::class));
    }
}
