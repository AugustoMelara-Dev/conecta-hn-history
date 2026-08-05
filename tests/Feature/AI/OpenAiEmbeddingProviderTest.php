<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Infrastructure\AI\Knowledge\OpenAiEmbeddingProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiEmbeddingProviderTest extends TestCase
{
    public function test_it_returns_a_dimension_checked_embedding_with_usage_metadata(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.embeddings_endpoint', 'https://api.openai.test/v1/embeddings');
        config()->set('ai.knowledge.model', 'text-embedding-test');
        config()->set('ai.knowledge.embedding_dimensions', 3);

        Http::fake([
            'https://api.openai.test/v1/embeddings' => Http::response([
                'data' => [['embedding' => [0.2, 0.3, 0.4]]],
                'model' => 'text-embedding-test',
                'usage' => ['prompt_tokens' => 7, 'total_tokens' => 7],
            ]),
        ]);

        $result = app(OpenAiEmbeddingProvider::class)->embed('Cafe hondureno');

        self::assertSame([0.2, 0.3, 0.4], $result->vector);
        self::assertSame('text-embedding-test', $result->model);
        self::assertSame(7, $result->inputTokens);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.test/v1/embeddings'
            && $request['model'] === 'text-embedding-test'
            && $request['input'] === 'Cafe hondureno'
            && $request['dimensions'] === 3
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_it_rejects_provider_vectors_with_the_wrong_dimensions(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.embeddings_endpoint', 'https://api.openai.test/v1/embeddings');
        config()->set('ai.knowledge.model', 'text-embedding-test');
        config()->set('ai.knowledge.embedding_dimensions', 3);

        Http::fake([
            '*' => Http::response([
                'data' => [['embedding' => [0.2, 0.3]]],
                'model' => 'text-embedding-test',
                'usage' => ['prompt_tokens' => 7, 'total_tokens' => 7],
            ]),
        ]);

        $this->expectException(AiMalformedResponseException::class);

        app(OpenAiEmbeddingProvider::class)->embed('Cafe hondureno');
    }
}
