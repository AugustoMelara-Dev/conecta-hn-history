<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

final class ConditionEvaluator
{
    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $payload
     */
    public function evaluate(?array $condition, array $payload): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $rule) {
                if (! is_array($rule) || ! $this->evaluate($rule, $payload)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $rule) {
                if (is_array($rule) && $this->evaluate($rule, $payload)) {
                    return true;
                }
            }

            return false;
        }

        $field = (string) ($condition['field'] ?? '');
        $operator = strtolower((string) ($condition['operator'] ?? 'equals'));
        $expected = $condition['value'] ?? null;
        $actual = data_get($payload, $field);

        return match ($operator) {
            'equals', '==' => $actual == $expected,
            'not_equals', '!=' => $actual != $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'exists' => $actual !== null && $actual !== '',
            'missing' => $actual === null || $actual === '',
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            default => false,
        };
    }
}

