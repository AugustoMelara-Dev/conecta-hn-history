<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\DTOs\AiProviderRequest;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\OpenAiStructuredTextProvider;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiStructuredTextProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.openai.api_key' => 'sk-structured-test',
            'services.openai.chat_completions_endpoint' => 'https://api.openai.test/v1/chat/completions',
            'ai.capabilities.public_discovery.models' => ['config-must-not-route'],
            'ai.schemas.public_discovery.answer.v1.json_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['answer', 'evidence'],
                'properties' => [
                    'answer' => ['type' => 'string'],
                    'evidence' => ['type' => 'array', 'items' => ['type' => 'object']],
                ],
            ],
            'ai.providers.openai.models.gpt-4o-mini' => [
                'input_usd_per_million' => 0.15,
                'output_usd_per_million' => 0.60,
            ],
        ]);
    }

    public function test_success_sends_strict_schema_and_maps_usage_and_exact_cost(): void
    {
        Http::fake([
            'https://api.openai.test/*' => Http::response($this->responsePayload()),
        ]);

        $result = (new OpenAiStructuredTextProvider)->generate($this->request());

        self::assertSame('openai', (new OpenAiStructuredTextProvider)->name());
        self::assertSame(['answer' => 'Grounded.', 'evidence' => []], $result->data);
        self::assertSame('gpt-4o-mini', $result->model);
        self::assertSame(1000, $result->inputTokens);
        self::assertSame(500, $result->outputTokens);
        self::assertEqualsWithDelta(0.00045, $result->costUsd, 0.000000001);
        self::assertSame('stop', $result->finishReason);

        $schemas = config('ai.schemas');
        $expectedSchema = is_array($schemas)
            ? $schemas['public_discovery.answer.v1']['json_schema']
            : null;

        Http::assertSent(function (Request $request) use ($expectedSchema): bool {
            return $request->url() === 'https://api.openai.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-structured-test')
                && $request['model'] === 'gpt-4o-mini'
                && $request['temperature'] === 0
                && $request['max_tokens'] === 600
                && $request['response_format']['type'] === 'json_schema'
                && $request['response_format']['json_schema']['strict'] === true
                && $request['response_format']['json_schema']['name'] === 'public_discovery_answer_v1'
                && $request['response_format']['json_schema']['schema'] === $expectedSchema
                && $request['messages'] === [
                    ['role' => 'system', 'content' => 'Use evidence only.'],
                    ['role' => 'user', 'content' => 'What can I buy?'],
                ];
        });
    }

    public function test_millisecond_timeout_is_forwarded_as_fractional_seconds(): void
    {
        $pending = \Mockery::mock(PendingRequest::class);
        $pending->shouldReceive('acceptJson')->once()->andReturnSelf();
        $pending->shouldReceive('timeout')->once()->with(0.25)->andReturnSelf();
        $pending->shouldReceive('post')->once()->andReturn(new Response(new Psr7Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($this->responsePayload(), JSON_THROW_ON_ERROR),
        )));

        Http::shouldReceive('withToken')
            ->once()
            ->with('sk-structured-test')
            ->andReturn($pending);

        (new OpenAiStructuredTextProvider)->generate($this->request(timeoutMilliseconds: 250));
    }

    public function test_http_failure_is_sanitized_without_previous_exception(): void
    {
        Http::fake([
            'https://api.openai.test/*' => Http::response([
                'error' => ['message' => 'SECRET provider payload'],
            ], 401),
        ]);

        try {
            (new OpenAiStructuredTextProvider)->generate($this->request());
            self::fail('HTTP provider failure must be rejected.');
        } catch (AiProviderUnavailableException $exception) {
            self::assertSame('OpenAI provider is temporarily unavailable.', $exception->getMessage());
            self::assertStringNotContainsString('SECRET', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            Http::assertSentCount(1);
        }
    }

    public function test_refusal_is_rejected_as_sanitized_malformed_output(): void
    {
        $payload = $this->responsePayload();
        $payload['choices'][0]['message'] = ['refusal' => 'SECRET refusal reason'];
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI declined or omitted the structured response.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_malformed_json_is_rejected(): void
    {
        $payload = $this->responsePayload();
        $payload['choices'][0]['message']['content'] = '{"answer":';
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI returned malformed structured content.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_missing_usage_is_rejected(): void
    {
        $payload = $this->responsePayload();
        unset($payload['usage']);
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI response usage metadata is invalid.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_non_string_content_is_rejected(): void
    {
        $payload = $this->responsePayload();
        $payload['choices'][0]['message']['content'] = ['unexpected'];
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI returned no structured content.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_list_json_is_rejected_as_non_object_content(): void
    {
        $payload = $this->responsePayload();
        $payload['choices'][0]['message']['content'] = '[]';
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI structured content is not an object.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_negative_usage_is_rejected(): void
    {
        $payload = $this->responsePayload();
        $payload['usage']['prompt_tokens'] = -1;
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI response usage metadata is invalid.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_missing_finish_reason_is_rejected(): void
    {
        $payload = $this->responsePayload();
        unset($payload['choices'][0]['finish_reason']);
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiMalformedResponseException::class);
        $this->expectExceptionMessage('OpenAI response usage metadata is invalid.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    public function test_unpriced_returned_model_fails_closed(): void
    {
        $payload = $this->responsePayload();
        $payload['model'] = 'gpt-unpriced-snapshot';
        Http::fake(['https://api.openai.test/*' => Http::response($payload)]);

        $this->expectException(AiProviderUnavailableException::class);
        $this->expectExceptionMessage('OpenAI model pricing is not configured.');

        (new OpenAiStructuredTextProvider)->generate($this->request());
    }

    private function request(int $timeoutMilliseconds = 15000): AiProviderRequest
    {
        return new AiProviderRequest(
            correlationId: 'correlation-1',
            capability: 'public_discovery',
            modelKey: 'gpt-4o-mini',
            systemPrompt: 'Use evidence only.',
            userInput: 'What can I buy?',
            timeoutMilliseconds: $timeoutMilliseconds,
            maxOutputTokens: 600,
            outputSchema: 'public_discovery.answer.v1',
        );
    }

    /** @return array<string, mixed> */
    private function responsePayload(): array
    {
        return [
            'choices' => [[
                'message' => [
                    'content' => '{"answer":"Grounded.","evidence":[]}',
                ],
                'finish_reason' => 'stop',
            ]],
            'model' => 'gpt-4o-mini',
            'usage' => [
                'prompt_tokens' => 1000,
                'completion_tokens' => 500,
            ],
        ];
    }
}
