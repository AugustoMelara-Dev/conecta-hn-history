<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

/**
 * ConditionType - Operadores para evaluar condiciones en workflows.
 */
enum ConditionType: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Changed = 'changed';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case DateAfter = 'date_after';
    case DateBefore = 'date_before';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'Es igual a',
            self::NotEquals => 'No es igual a',
            self::Contains => 'Contiene',
            self::GreaterThan => 'Mayor que',
            self::LessThan => 'Menor que',
            self::Changed => 'Cambió',
            self::IsTrue => 'Es verdadero',
            self::IsFalse => 'Es falso',
            self::DateAfter => 'Fecha posterior a',
            self::DateBefore => 'Fecha anterior a',
        };
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        return match ($this) {
            self::Equals => $actual === $expected,
            self::NotEquals => $actual !== $expected,
            self::Contains => is_string($actual) && str_contains($actual, (string) $expected),
            self::GreaterThan => (float) $actual > (float) $expected,
            self::LessThan => (float) $actual < (float) $expected,
            self::Changed => $actual !== $expected,
            self::IsTrue => (bool) $actual === true,
            self::IsFalse => (bool) $actual === false,
            self::DateAfter => strtotime((string) $actual) > strtotime((string) $expected),
            self::DateBefore => strtotime((string) $actual) < strtotime((string) $expected),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
