<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Shared\Contracts\AiProfileGeneratorInterface;
use App\Application\Tenant\ResultsProfileData;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
use App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiProfileGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration Tests for AI Enrichment Pipeline.
 *
 * These tests exercise the queued job against the current container bindings
 * and the live handler contract without Mockery state pollution.
 */
class AiEnrichmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-api-key']);

        $this->app->bind(AiProfileGeneratorInterface::class, AiProfileGeneratorService::class);

        $this->user = User::factory()->create(['role' => 'seller']);
    }

    private function createTenant(array $overrides = []): Tenant
    {
        static $sequence = 1000;

        return Tenant::forceCreate(array_merge([
            'id' => (string) ++$sequence,
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.uniqid(),
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
            'business_name_clean' => 'Pulperia Don Mario',
            'tagline' => 'Tu tienda de confianza',
            'short_description' => 'La mejor pulperia de Tegucigalpa',
            'full_description_html' => '<p>Bienvenidos a nuestra pulperia.</p>',
            'seo_keywords' => ['pulperia', 'tegucigalpa', 'tienda'],
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

    public function test_job_enriches_tenant_with_real_service(): void
    {
        $this->fakeOpenAiResponse('success');

        $tenant = $this->createTenant([
            'name' => 'pulperia don mario',
        ]);

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        $job = new EnhanceTenantProfileJob($data);
        $job->handle(app(\App\Application\Tenant\Handlers\EnhanceTenantProfileHandler::class));

        $tenant->refresh();

        $this->assertSame('pulperia don mario', $tenant->name);
        $this->assertTrue($tenant->is_ai_enhanced);
        Http::assertSentCount(1);
    }

    public function test_job_handles_flagged_response_with_current_domain_flow(): void
    {
        $this->fakeOpenAiResponse('flagged');

        $tenant = $this->createTenant([
            'name' => 'Suspicious Business',
        ]);

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        $job = new EnhanceTenantProfileJob($data);
        $job->handle(app(\App\Application\Tenant\Handlers\EnhanceTenantProfileHandler::class));

        $tenant->refresh();

        $this->assertTrue($tenant->is_active);
        $this->assertTrue($tenant->is_ai_enhanced);
        Http::assertSentCount(1);
    }

    public function test_job_throws_for_nonexistent_tenant(): void
    {
        $this->expectException(TenantDomainException::class);

        $data = new ResultsProfileData(tenantId: '999999');
        $job = new EnhanceTenantProfileJob($data);
        $job->handle(app(\App\Application\Tenant\Handlers\EnhanceTenantProfileHandler::class));
    }

    public function test_job_rejects_already_enriched_tenant(): void
    {
        $this->fakeOpenAiResponse('success');

        $tenant = $this->createTenant([
            'is_ai_enhanced' => true,
            'name' => 'Already Enriched Tenant',
        ]);

        $this->expectException(TenantDomainException::class);

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        $job = new EnhanceTenantProfileJob($data);
        $job->handle(app(\App\Application\Tenant\Handlers\EnhanceTenantProfileHandler::class));
    }

    public function test_job_can_be_dispatched_to_queue(): void
    {
        Bus::fake();

        $tenant = $this->createTenant();

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        Bus::dispatch(new EnhanceTenantProfileJob($data));

        Bus::assertDispatched(EnhanceTenantProfileJob::class, function ($job) use ($tenant) {
            return $job->uniqueId() === 'enhance_'.$tenant->id;
        });
    }

    public function test_job_has_correct_configuration(): void
    {
        $data = new ResultsProfileData(tenantId: '1');
        $job = new EnhanceTenantProfileJob($data);

        $this->assertSame(3, $job->tries);
        $this->assertSame(180, $job->timeout);
        $this->assertSame(3600, $job->uniqueFor);
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

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        $job = new EnhanceTenantProfileJob($data);
        $job->handle(app(\App\Application\Tenant\Handlers\EnhanceTenantProfileHandler::class));
    }
}
