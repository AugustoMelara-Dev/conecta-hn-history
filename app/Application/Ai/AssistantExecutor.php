<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Ai\Contracts\AssistantInterface;
use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\DTOs\AssistantExecutionContext;
use App\Models\AiAssistantRun;
use Illuminate\Support\Facades\Log;

/**
 * AssistantExecutor - Ejecuta AI assistants con audit + cost tracking.
 */
class AssistantExecutor
{
    /** @var array<string, AssistantInterface> */
    private array $assistants = [];

    public function register(AssistantInterface $assistant): void
    {
        $this->assistants[$assistant->key()] = $assistant;
    }

    public function has(string $key): bool
    {
        return isset($this->assistants[$key]);
    }

    /**
     * @return array<string, AssistantInterface>
     */
    public function all(): array
    {
        return $this->assistants;
    }

    public function get(string $key): AssistantInterface
    {
        if (! isset($this->assistants[$key])) {
            throw new \InvalidArgumentException("Assistant not registered: {$key}");
        }

        return $this->assistants[$key];
    }

    public function execute(string $key, array $input, AssistantExecutionContext $context): AssistantResult
    {
        $assistant = $this->get($key);
        $startTime = microtime(true);

        try {
            $result = $assistant->execute($input, $context);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $result = new AssistantResult(
                success: $result->success,
                output: $result->output,
                costUsd: $result->success && $result->costUsd <= 0 ? $assistant->estimateCost() : $result->costUsd,
                durationMs: $durationMs,
                errorMessage: $result->errorMessage,
                metadata: array_merge($result->metadata, ['assistant_key' => $key]),
            );

            $this->audit($key, $input, $result, $context);

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $result = AssistantResult::failure($e->getMessage(), ['assistant_key' => $key]);
            $this->audit($key, $input, $result, $context);

            return $result;
        }
    }

    private function audit(string $key, array $input, AssistantResult $result, AssistantExecutionContext $context): void
    {
        try {
            AiAssistantRun::updateOrCreate([
                'tenant_id' => $context->tenantId,
                'assistant_key' => $key,
                'correlation_id' => $context->correlationId,
            ], [
                'user_id' => $context->actorId,
                'assistant_key' => $key,
                'prompt' => null,
                'response' => null,
                'cost_usd' => $result->costUsd,
                'status' => $result->success ? 'completed' : (string) ($result->metadata['status'] ?? 'failed'),
                'duration_ms' => $result->durationMs,
                'error_message' => $result->success ? null : $result->errorMessage,
                'metadata' => array_merge($result->metadata, [
                    'correlation_id' => $context->correlationId,
                    'input_hash' => hash('sha256', serialize($input)),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to audit AI assistant run', ['error' => $e->getMessage()]);
        }
    }
}
