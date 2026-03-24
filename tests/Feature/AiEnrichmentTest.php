<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Shared\Contracts\AiProfileGeneratorInterface;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Tenant\Handlers\EnhanceTenantProfileHandler;
use App\Application\Tenant\ResultsProfileData;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob;
use App\Models\Tenant;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\OpeningHours\OpeningHours;
use Tests\TestCase;

final class AiEnrichmentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-api-key']);
    }

    #[Test]
    public function service_throws_exception_when_api_key_is_not_configured(): void
    {
        config(['services.openai.key' => null]);

        $service = new \App\Services\AiProfileGeneratorService;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API key is not configured');

        $service->generate('Test', 'Description', 'Category');
    }

    #[Test]
    public function service_sanitizes_xss_from_html_output(): void
    {
        $this->fakeSuccessfulOpenAiResponse([
            'business_name_clean' => 'Test<script>alert(1)</script>',
            'tagline' => 'Tagline<img onerror="alert(1)">',
            'short_description' => 'Short<iframe>evil</iframe>',
            'full_description_html' => '<p>Safe</p><script>evil</script><ul><li>Item</li></ul>',
            'seo_keywords' => ['test'],
            'risk_analysis' => 'None',
        ]);

        $service = new \App\Services\AiProfileGeneratorService;
        $result = $service->generate('Test', 'Desc', 'Cat');

        self::assertStringNotContainsString('<script>', (string) $result['business_name_clean']);
        self::assertStringNotContainsString('onerror', (string) $result['tagline']);
        self::assertStringNotContainsString('<iframe>', (string) $result['short_description']);
        self::assertStringNotContainsString('<script>', (string) $result['full_description_html']);
        self::assertStringContainsString('<p>Safe</p>', (string) $result['full_description_html']);
        self::assertStringContainsString('<ul><li>Item</li></ul>', (string) $result['full_description_html']);
    }

    #[Test]
    public function service_handles_rate_limit_response_correctly(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Rate limited'],
            ], 429, ['Retry-After' => '60']),
        ]);

        $service = new \App\Services\AiProfileGeneratorService;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('rate limited');

        $service->generate('Test', 'Desc', 'Cat');
    }

    #[Test]
    public function service_validates_required_keys_in_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['status' => 'success'], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new \App\Services\AiProfileGeneratorService;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing required keys');

        $service->generate('Test', 'Desc', 'Cat');
    }

    #[Test]
    public function service_returns_valid_response_on_success(): void
    {
        $this->fakeSuccessfulOpenAiResponse();

        $service = new \App\Services\AiProfileGeneratorService;
        $result = $service->generate('pulperia don mario', 'tienda local', 'Comercio');

        self::assertSame('success', $result['status']);
        self::assertSame('Pulperia Don Mario', $result['business_name_clean']);
        self::assertIsArray($result['seo_keywords']);
        self::assertContains('pulperia', $result['seo_keywords']);
    }

    #[Test]
    public function service_handles_flagged_content_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'status' => 'flagged',
                                'business_name_clean' => 'Suspicious Business',
                                'tagline' => '',
                                'short_description' => '',
                                'full_description_html' => '',
                                'seo_keywords' => [],
                                'risk_analysis' => 'Detected references to prohibited substances',
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new \App\Services\AiProfileGeneratorService;
        $result = $service->generate('Suspicious', 'drugs available', 'Unknown');

        self::assertSame('flagged', $result['status']);
        self::assertStringContainsString('prohibited', (string) $result['risk_analysis']);
    }

    #[Test]
    public function job_dispatches_command_to_handler_even_when_tenant_is_missing(): void
    {
        $data = new ResultsProfileData(tenantId: '999999');
        $job = new EnhanceTenantProfileJob($data);

        $repository = new InMemoryTenantRepository;
        $aiService = new FakeAiProfileGenerator([
            'status' => 'success',
            'business_name_clean' => 'Ignored',
            'tagline' => 'Ignored',
            'short_description' => 'Ignored',
            'seo_keywords' => [],
            'tags' => [],
        ]);
        $transactionManager = new InlineTransactionManager;
        $logger = new RecordingApplicationLogger;
        $scrubber = new PassthroughScrubber;

        $handler = new EnhanceTenantProfileHandler(
            $repository,
            $aiService,
            $transactionManager,
            $logger,
            $scrubber,
        );

        $this->expectException(TenantDomainException::class);

        try {
            $job->handle($handler);
        } finally {
            self::assertSame(1, $transactionManager->calls);
            self::assertSame(0, $repository->saveCalls);
            self::assertSame(0, $aiService->calls);
            self::assertCount(0, $logger->infos);
        }
    }

    #[Test]
    public function job_throws_when_tenant_was_already_ai_enhanced(): void
    {
        $tenant = $this->createTestTenant([
            'is_ai_enhanced' => true,
        ]);

        $repository = new InMemoryTenantRepository([
            (string) $tenant->id => TenantEntity::fromStateArray([
                'id' => (string) $tenant->id,
                'name' => (string) $tenant->name,
                'domain' => (string) $tenant->slug,
                'is_active' => true,
                'is_ai_enhanced' => true,
            ]),
        ]);

        $aiService = new FakeAiProfileGenerator([
            'status' => 'success',
            'business_name_clean' => (string) $tenant->name,
            'tagline' => 'Tagline',
            'short_description' => 'Description',
            'seo_keywords' => ['tag'],
            'tags' => ['tag'],
        ]);
        $transactionManager = new InlineTransactionManager;
        $logger = new RecordingApplicationLogger;
        $scrubber = new PassthroughScrubber;

        $handler = new EnhanceTenantProfileHandler(
            $repository,
            $aiService,
            $transactionManager,
            $logger,
            $scrubber,
        );

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        $job = new EnhanceTenantProfileJob($data);

        $this->expectException(TenantDomainException::class);

        try {
            $job->handle($handler);
        } finally {
            self::assertSame(1, $transactionManager->calls);
            self::assertSame(1, $aiService->calls);
            self::assertSame([(string) $tenant->name, '', 'General'], $aiService->lastPayload);
            self::assertSame(0, $repository->saveCalls);
            self::assertCount(0, $logger->infos);
        }
    }

    #[Test]
    public function job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $tenant = $this->createTestTenant();

        $data = new ResultsProfileData(tenantId: (string) $tenant->id);
        EnhanceTenantProfileJob::dispatch($data);

        Queue::assertPushed(EnhanceTenantProfileJob::class, function (EnhanceTenantProfileJob $job) use ($tenant): bool {
            return $job->uniqueId() === 'enhance_'.$tenant->id;
        });
    }

    #[Test]
    public function job_has_correct_queue_configuration(): void
    {
        $data = new ResultsProfileData(tenantId: '1');
        $job = new EnhanceTenantProfileJob($data);

        self::assertSame(3, $job->tries);
        self::assertSame(180, $job->timeout);
        self::assertSame(3600, $job->uniqueFor);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTestTenant(array $overrides = []): Tenant
    {
        $user = User::factory()->create(['role' => 'seller']);

        return Tenant::forceCreate(array_merge([
            'user_id' => $user->id,
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.uniqid(),
            'is_active' => true,
            'is_ai_enhanced' => false,
            'subscription_status' => 'active',
            'plan_type' => 'pro',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeSuccessfulOpenAiResponse(array $overrides = []): void
    {
        $defaultResponse = [
            'status' => 'success',
            'business_name_clean' => 'Pulperia Don Mario',
            'tagline' => 'Tu tienda de confianza',
            'short_description' => 'La mejor pulperia de Tegucigalpa',
            'full_description_html' => '<p>Bienvenidos a nuestra pulperia.</p>',
            'seo_keywords' => ['pulperia', 'tegucigalpa', 'tienda'],
            'risk_analysis' => 'Low risk - standard retail business',
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(array_merge($defaultResponse, $overrides), JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);
    }
}

final class InMemoryTenantRepository implements TenantRepositoryInterface
{
    /**
     * @var array<string, TenantEntity>
     */
    private array $tenants;

    public int $saveCalls = 0;

    /**
     * @param  array<string, TenantEntity>  $tenants
     */
    public function __construct(array $tenants = [])
    {
        $this->tenants = $tenants;
    }

    public function findById(string $id): ?TenantEntity
    {
        return $this->tenants[$id] ?? null;
    }

    public function findByIdForUpdate(string $id): ?TenantEntity
    {
        return $this->findById($id);
    }

    public function findBySlug(string $slug): ?TenantEntity
    {
        return null;
    }

    public function existsBySlug(string $slug): bool
    {
        return false;
    }

    public function findByDomain(string $domain): ?TenantEntity
    {
        return null;
    }

    public function getFeatured(int $limit = 12): array
    {
        return [];
    }

    public function countActive(): int
    {
        return 0;
    }

    public function getOpeningHours(string $tenantId): OpeningHours
    {
        return OpeningHours::create([]);
    }

    public function create(array $attributes): TenantEntity
    {
        throw new Exception('Not implemented in test fake.');
    }

    public function save(TenantEntity $tenant): TenantEntity
    {
        $this->saveCalls++;
        $this->tenants[$tenant->id()] = $tenant;

        return $tenant;
    }

    public function updateOptimistically(TenantEntity $tenant): void
    {
        $this->tenants[$tenant->id()] = $tenant;
    }

    public function delete(TenantEntity $tenant): void
    {
        unset($this->tenants[$tenant->id()]);
    }
}

final class FakeAiProfileGenerator implements AiProfileGeneratorInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $response;

    public int $calls = 0;

    /**
     * @var list<string>|null
     */
    public ?array $lastPayload = null;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function generate(string $rawName, string $rawDescription, string $rawCategory): array
    {
        $this->calls++;
        $this->lastPayload = [$rawName, $rawDescription, $rawCategory];

        return $this->response;
    }
}

final class InlineTransactionManager implements TransactionManagerInterface
{
    public int $calls = 0;

    public function transaction(callable $callback): mixed
    {
        $this->calls++;

        return $callback();
    }
}

final class RecordingApplicationLogger implements LoggerInterface
{
    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $infos = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $warnings = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $errors = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $debugs = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $criticals = [];

    public function info(string $message, array $context = []): void
    {
        $this->infos[] = ['message' => $message, 'context' => $context];
    }

    public function warning(string $message, array $context = []): void
    {
        $this->warnings[] = ['message' => $message, 'context' => $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->errors[] = ['message' => $message, 'context' => $context];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->debugs[] = ['message' => $message, 'context' => $context];
    }

    public function critical(string $message, array $context = []): void
    {
        $this->criticals[] = ['message' => $message, 'context' => $context];
    }
}

final class PassthroughScrubber implements DataScrubberInterface
{
    public function scrub(array|object $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        return get_object_vars($payload);
    }
}
