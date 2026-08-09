<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\ActionType;

/**
 * ActionRegistry - Mapea ActionType ? callable que ejecuta el payload.
 *
 * Cada action handler debe:
 *   - Ser idempotente (input.idempotency_key check)
 *   - Retornar array con status, output, errors
 *   - Auditar la ejecución
 */
class ActionRegistry
{
    /** @var array<string, callable> */
    private array $actions = [];

    public function register(ActionType $type, callable $handler): void
    {
        $this->actions[$type->value] = $handler;
    }

    public function execute(ActionType $type, array $payload, array $context = []): array
    {
        $handler = $this->actions[$type->value] ?? null;

        if ($handler === null) {
            return [
                'status' => 'skipped',
                'output' => null,
                'errors' => ["Action {$type->value} not registered"],
            ];
        }

        try {
            $result = $handler($payload, $context);

            return [
                'status' => $result['status'] ?? 'success',
                'output' => $result['output'] ?? null,
                'errors' => $result['errors'] ?? [],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'output' => null,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    public function has(ActionType $type): bool
    {
        return isset($this->actions[$type->value]);
    }

    /**
     * @return array<int, string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->actions);
    }
}
