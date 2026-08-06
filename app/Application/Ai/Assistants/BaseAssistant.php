<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantInterface;
use App\Application\Ai\Contracts\AssistantResult;

/**
 * BaseAssistant - Implementaci�n com�n para assistants.
 *
 * En producci�n, los assistants usan OpenAI client.
 * En dev/test, retornan responses mock deterministas.
 */
abstract class BaseAssistant implements AssistantInterface
{
    abstract public function key(): string;
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function estimateCost(): float;
    abstract public function category(): string;
    abstract public function execute(array $input): AssistantResult;

    public function guardrails(): array
    {
        return [
            'no_pii_storage' => 'No almacena datos personales identificables fuera del log de auditor�a.',
            'cost_limit' => "Costo por ejecuci�n <= \${$this->estimateCost()}",
            'async_safe' => 'Puede ejecutarse de forma as�ncrona v�a queue.',
            'input_validation' => 'Valida input antes de invocar el modelo.',
        ];
    }

    /**
     * En producci�n: $client = \OpenAI::client(config('services.openai.key'));
     * $response = $client->chat()->create([...]);
     * Por ahora retornamos un placeholder.
     */
    protected function mockOutput(array $input, array $overrides = []): AssistantResult
    {
        $output = array_merge([
            'status' => 'completed',
            'mock' => true,
            'timestamp' => now()->toIso8601String(),
            'input_keys' => array_keys($input),
        ], $overrides);

        return AssistantResult::success(
            output: $output,
            cost: $this->estimateCost(),
            durationMs: 100,
            metadata: ['assistant' => $this->key()],
        );
    }
}



