<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Contracts\AiProvider;
use App\Application\Ai\DTOs\AiProviderRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class OpenAiStructuredTextProvider implements AiProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function generate(AiProviderRequest $request): AiProviderResult
    {
        try {
            $apiKey = trim((string) config('services.openai.api_key', ''));
            $endpoint = trim((string) config('services.openai.chat_completions_endpoint', ''));
            $model = $request->modelKey;
            $schemas = config('ai.schemas', []);
            $schemaDefinition = is_array($schemas) ? ($schemas[$request->outputSchema] ?? null) : null;
            $schema = is_array($schemaDefinition) ? ($schemaDefinition['json_schema'] ?? null) : null;

            if ($apiKey === '' || ! $this->isHttpsUrl($endpoint)) {
                throw new AiProviderUnavailableException('OpenAI provider is not configured.');
            }

            if (trim($model) === '') {
                throw new AiProviderUnavailableException('OpenAI model policy is not configured.');
            }

            if (! is_array($schema) || ($schema['type'] ?? null) !== 'object') {
                throw new AiProviderUnavailableException('OpenAI output schema is not configured.');
            }

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(max(0.001, $request->timeoutMilliseconds / 1000))
                ->post($endpoint, [
                    'model' => $model,
                    'temperature' => 0,
                    'max_tokens' => $request->maxOutputTokens,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => $this->schemaName($request->outputSchema),
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                    'messages' => [
                        ['role' => 'system', 'content' => $request->systemPrompt],
                        ['role' => 'user', 'content' => $request->userInput],
                    ],
                ]);

            if (! $response->successful()) {
                throw new AiProviderUnavailableException('OpenAI provider is temporarily unavailable.');
            }

            $raw = $response->json();
            if (! is_array($raw)) {
                throw new AiMalformedResponseException('OpenAI returned an invalid response.');
            }

            $message = $raw['choices'][0]['message'] ?? null;
            if (! is_array($message) || isset($message['refusal'])) {
                throw new AiMalformedResponseException('OpenAI declined or omitted the structured response.');
            }

            $content = $message['content'] ?? null;
            if (! is_string($content) || trim($content) === '') {
                throw new AiMalformedResponseException('OpenAI returned no structured content.');
            }

            try {
                $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new AiMalformedResponseException('OpenAI returned malformed structured content.');
            }

            if (! is_array($data) || array_is_list($data)) {
                throw new AiMalformedResponseException('OpenAI structured content is not an object.');
            }

            $responseModel = $raw['model'] ?? null;
            $usage = $raw['usage'] ?? null;
            $finishReason = $raw['choices'][0]['finish_reason'] ?? null;

            if (! is_string($responseModel) || trim($responseModel) === ''
                || ! is_array($usage)
                || ! array_key_exists('prompt_tokens', $usage)
                || ! array_key_exists('completion_tokens', $usage)
                || ! is_int($usage['prompt_tokens'])
                || ! is_int($usage['completion_tokens'])
                || $usage['prompt_tokens'] < 0
                || $usage['completion_tokens'] < 0
                || ! is_string($finishReason)
                || trim($finishReason) === '') {
                throw new AiMalformedResponseException('OpenAI response usage metadata is invalid.');
            }

            [$inputRate, $outputRate] = $this->modelRates($responseModel);
            $cost = (($usage['prompt_tokens'] * $inputRate) + ($usage['completion_tokens'] * $outputRate)) / 1_000_000;

            return new AiProviderResult(
                data: $data,
                model: $responseModel,
                inputTokens: $usage['prompt_tokens'],
                outputTokens: $usage['completion_tokens'],
                costUsd: $cost,
                finishReason: $finishReason,
            );
        } catch (AiProviderUnavailableException|AiMalformedResponseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AiProviderUnavailableException('OpenAI provider is temporarily unavailable.');
        }
    }

    /** @return array{float, float} */
    private function modelRates(string $model): array
    {
        $pricing = config("ai.providers.openai.models.{$model}");
        $inputRate = is_array($pricing) ? ($pricing['input_usd_per_million'] ?? null) : null;
        $outputRate = is_array($pricing) ? ($pricing['output_usd_per_million'] ?? null) : null;

        if (! is_numeric($inputRate) || ! is_numeric($outputRate)
            || ! is_finite((float) $inputRate) || ! is_finite((float) $outputRate)
            || (float) $inputRate <= 0 || (float) $outputRate <= 0) {
            throw new AiProviderUnavailableException('OpenAI model pricing is not configured.');
        }

        return [(float) $inputRate, (float) $outputRate];
    }

    private function isHttpsUrl(string $endpoint): bool
    {
        return filter_var($endpoint, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) === 'https';
    }

    private function schemaName(string $schema): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $schema);

        return mb_substr(is_string($name) ? $name : 'structured_response', 0, 64);
    }
}
