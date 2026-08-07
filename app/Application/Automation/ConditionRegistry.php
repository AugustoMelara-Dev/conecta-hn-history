<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\ConditionType;

/**
 * ConditionRegistry - Evalúa condiciones contra un payload.
 */
class ConditionRegistry
{
    /**
     * Evalúa una sola condición.
     */
    public function evaluate(ConditionType $type, mixed $actual, mixed $expected): bool
    {
        return $type->evaluate($actual, $expected);
    }

    /**
     * Evalúa un grupo de condiciones (AND lógico).
     *
     * @param array<int, array{type: string, actual: mixed, expected: mixed}> $conditions
     */
    public function evaluateAll(array $conditions): bool
    {
        foreach ($conditions as $cond) {
            $type = ConditionType::tryFrom($cond['type'] ?? '');
            if ($type === null) {
                return false;
            }
            if (! $this->evaluate($type, $cond['actual'] ?? null, $cond['expected'] ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evalúa un grupo de condiciones (OR lógico).
     *
     * @param array<int, array{type: string, actual: mixed, expected: mixed}> $conditions
     */
    public function evaluateAny(array $conditions): bool
    {
        foreach ($conditions as $cond) {
            $type = ConditionType::tryFrom($cond['type'] ?? '');
            if ($type !== null && $this->evaluate($type, $cond['actual'] ?? null, $cond['expected'] ?? null)) {
                return true;
            }
        }
        return false;
    }
}
