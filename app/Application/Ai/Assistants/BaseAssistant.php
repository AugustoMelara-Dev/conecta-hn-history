<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantInterface;
use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\Contracts\AssistantRuntime;
use App\Application\Ai\DTOs\AssistantExecutionContext;

/**
 * BaseAssistant - Implementación común para assistants.
 *
 * En producción, los assistants usan OpenAI client.
 * En dev/test, retornan responses mock deterministas.
 */
abstract class BaseAssistant implements AssistantInterface
{
    public function __construct(private readonly AssistantRuntime $runtime) {}

    abstract public function key(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function estimateCost(): float;

    abstract public function category(): string;

    abstract public function execute(array $input, ?AssistantExecutionContext $context = null): AssistantResult;

    public function guardrails(): array
    {
        return [
            'no_pii_storage' => 'No almacena datos personales identificables fuera del log de auditoría.',
            'cost_limit' => "Costo por ejecución <= \${$this->estimateCost()}",
            'async_safe' => 'Puede ejecutarse de forma asíncrona vía queue.',
            'input_validation' => 'Valida input antes de invocar el modelo.',
        ];
    }

    /**
     * En producción: $client = \OpenAI::client(config('services.openai.key'));
     * $response = $client->chat()->create([...]);
     * Por ahora retornamos un placeholder.
     */
    protected function executeCanonical(array $input, ?AssistantExecutionContext $context): AssistantResult
    {
        if ($context === null) {
            return AssistantResult::failure('AI assistant context is required.', ['status' => 'unavailable']);
        }

        return $this->runtime->execute($this->key(), $input, $context);
    }
}
