<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Ai\Contracts\AssistantInterface;
use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\Contracts\AuditRecord;
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

    public function execute(string $key, array $input, ?string $tenantId = null, ?string $userId = null): AssistantResult
    {
        $assistant = $this->get($key);
        $startTime = microtime(true);

        try {
            $result = $assistant->execute($input);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $result = new AssistantResult(
                success: $result->success,
                output: $result->output,
                costUsd: $result->costUsd ?: $assistant->estimateCost(),
                durationMs: $durationMs,
                errorMessage: $result->errorMessage,
                metadata: array_merge($result->metadata, ['assistant_key' => $key]),
            );

            $this->audit($key, $input, $result, $tenantId, $userId);

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $result = AssistantResult::failure($e->getMessage(), ['assistant_key' => $key]);
            $this->audit($key, $input, $result, $tenantId, $userId);

            return $result;
        }
    }

    private function audit(string $key, array $input, AssistantResult $result, ?string $tenantId, ?string $userId): void
    {
        try {
            AiAssistantRun::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'assistant_key' => $key,
                'prompt' => json_encode($input, JSON_UNESCAPED_UNICODE),
                'response' => is_string($result->output) ? $result->output : json_encode($result->output, JSON_UNESCAPED_UNICODE),
                'cost_usd' => $result->costUsd,
                'status' => $result->success ? 'completed' : 'failed',
                'duration_ms' => $result->durationMs,
                'error_message' => $result->errorMessage,
                'metadata' => $result->metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to audit AI assistant run', ['error' => $e->getMessage()]);
        }
    }
}
