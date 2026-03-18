<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OpenAIServiceTest - The Fortress Validation Suite
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  QUALITY ASSURANCE: 100% COVERAGE OF CRITICAL PATHS                       ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║  Scenario 1: Happy Path (Valid JSON + DB Log)                             ║
 * ║  Scenario 2: Provider Death (5xx Retry Exhaustion)                        ║
 * ║  Scenario 3: Data Integrity Guard (Malformed JSON Rejection)              ║
 * ║  Scenario 4: Type Safety Guard (Schema Validation)                        ║
 * ║  Scenario 5: Security / XSS Prevention (Sanitization)                     ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */
final class OpenAIServiceTest extends TestCase
{
    use RefreshDatabase;

    private OpenAIService $service;

    private User $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'sk-fake-key']);

        $this->user = User::factory()->create();
        $tenant = Tenant::factory()
            ->forUser($this->user)
            ->create([
                'ai_balance' => 10.000000,
                'ai_reserved_balance' => 0.000000,
            ]);

        $this->service = new OpenAIService(app(BudgetEnforcer::class));
        $this->tenantId = (string) $tenant->id;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_successfully_generates_and_logs_profile_on_valid_response(): void
    {
        $expectedPayload = [
            'description' => '<p>Optimized description</p>',
            'seo_title' => 'Best SEO Title',
            'seo_description' => 'Meta desc',
            'tags' => ['marketing', 'seo', 'growth'], // Min 3 required
            'category_suggestion' => 'Marketing Agency',
        ];

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'choices' => [['message' => ['content' => json_encode($expectedPayload), 'role' => 'assistant']]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ], 200),
        ]);

        $result = $this->service->generateStructuredProfile('My Biz', 'Desc', $this->user->id, $this->tenantId);

        $this->assertSame($expectedPayload, $result);
        $this->assertDatabaseHas('ai_usage_logs', ['cost' => '0.000750']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_unavailable_exception_after_exhausting_retries_on_500_errors(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push('Server Error', 500)
                ->push('Server Error', 503)
                ->push('Connection Error', 502),
        ]);

        $this->expectException(AiProviderUnavailableException::class);
        $this->service->generateStructuredProfile('Biz', 'Desc', $this->user->id, $this->tenantId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_malformed_exception_if_response_is_missing_keys(): void
    {
        $brokenPayload = ['description' => 'Missing stuff'];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($brokenPayload)]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ], 200),
        ]);

        $this->expectException(AiMalformedResponseException::class);
        $this->service->generateStructuredProfile('Biz', 'Desc', $this->user->id, $this->tenantId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scenario_4_it_enforces_strict_types_via_validator(): void
    {
        // Invalid Type: 'tags' is string "tag1", expected array
        $invalidTypePayload = [
            'description' => 'Valid desc',
            'seo_title' => 'Valid Title',
            'seo_description' => 'Valid Meta',
            'tags' => 'tag1, tag2', // VIOLATION
            'category_suggestion' => 'Cat',
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($invalidTypePayload)]]],
            ], 200),
        ]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('AI returned invalid schema:');

        $this->service->generateStructuredProfile('Biz', 'Desc', $this->user->id, $this->tenantId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scenario_5_it_sanitizes_dangerous_html_xss_vectors(): void
    {
        // Malicious Payload
        $maliciousPayload = [
            'description' => '<p>Safe intro</p><script>alert("XSS")</script><b>Bold</b><iframe src="evil.com"></iframe>',
            'seo_title' => 'Title',
            'seo_description' => 'Meta',
            'tags' => ['one', 'two', 'three'],
            'category_suggestion' => 'Cat',
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($maliciousPayload)]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ], 200),
        ]);

        $result = $this->service->generateStructuredProfile('Biz', 'Desc', $this->user->id, $this->tenantId);

        // Verification: Dangerous tags MUST be gone. Safe tags MUST remain.
        $this->assertStringNotContainsString('<script>', $result['description']);
        $this->assertStringNotContainsString('<iframe>', $result['description']);
        $this->assertStringContainsString('<p>Safe intro</p>', $result['description']);
        $this->assertStringContainsString('<b>Bold</b>', $result['description']);

        // Strip tags keeps content of script usually? Standard PHP behavior:
        // strip_tags('<script>alert("XSS")</script>') -> 'alert("XSS")'.
        // So we check text content remains but tag is gone.
        $this->assertStringContainsString('alert("XSS")', $result['description']);
    }
}
