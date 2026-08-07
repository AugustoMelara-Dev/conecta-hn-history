<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\TriggerType;

/**
 * TriggerRegistry - Mapea TriggerType ? callable que produce payload.
 *
 * Cada trigger devuelve un array con:
 *   - event_name: string
 *   - entity_type: string (contact, deal, order, etc.)
 *   - entity_id: string
 *   - data: array (datos específicos del evento)
 *   - timestamp: ISO 8601
 */
class TriggerRegistry
{
    /** @var array<string, callable> */
    private array $triggers = [];

    public function register(TriggerType $type, callable $handler): void
    {
        $this->triggers[$type->value] = $handler;
    }

    public function produce(TriggerType $type, array $context): array
    {
        $handler = $this->triggers[$type->value] ?? null;

        if ($handler === null) {
            return [
                'event_name' => $type->value,
                'entity_type' => $context['entity_type'] ?? 'unknown',
                'entity_id' => $context['entity_id'] ?? null,
                'data' => $context,
                'timestamp' => now()->toIso8601String(),
                'registered' => false,
            ];
        }

        return $handler($context);
    }

    public function has(TriggerType $type): bool
    {
        return isset($this->triggers[$type->value]);
    }

    /**
     * @return array<int, string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->triggers);
    }
}
