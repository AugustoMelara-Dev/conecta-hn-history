<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * LowStockSeverity - Enumeration for Inventory Alert Severity Levels
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  USAGE:                                                                 ║
 * ║    $severity = LowStockSeverity::Warning;                               ║
 * ║    $severity->value  // 'warning'                                       ║
 * ║    $severity->label()  // 'Warning'                                     ║
 * ║    $severity->icon()   // 'alert-triangle'                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum LowStockSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informational',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * Icon identifier for UI components.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Info => 'info-circle',
            self::Warning => 'alert-triangle',
            self::Critical => 'alert-octagon',
        };
    }

    /**
     * Color class for UI styling.
     */
    public function color(): string
    {
        return match ($this) {
            self::Info => 'blue',
            self::Warning => 'yellow',
            self::Critical => 'red',
        };
    }

    /**
     * Check if this severity should trigger immediate notification.
     */
    public function requiresImmediateAction(): bool
    {
        return $this === self::Critical;
    }

    /**
     * Create from string value with validation.
     *
     * @throws \ValueError If value is invalid
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    /**
     * Try to create from string, returning null on failure.
     */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get all valid values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
