<?php

declare(strict_types=1);
declare(strict_types=1);

namespace App\Enums;

/**
 * AnnouncementType - PHP 8.1 Backed Enum (ADR-002 Compliant)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADDING NEW TYPES:                                                      ║
 * ║  1. Add new case below                                                  ║
 * ║  2. Update AnnouncementRequest validation                               ║
 * ║  3. Update UI color mapping in frontend                                 ║
 * ║  NO DATABASE MIGRATION REQUIRED                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum AnnouncementType: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case PROMO = 'promo';
    case URGENT = 'urgent';

    /**
     * Default type for new announcements.
     */
    public const DEFAULT = self::INFO;

    /**
     * Get all valid type values as array.
     * Useful for validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get validation rule for Laravel.
     */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Check if a value is valid.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get CSS color class for this type.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::INFO => 'blue',
            self::WARNING => 'amber',
            self::PROMO => 'emerald',
            self::URGENT => 'red',
        };
    }

    /**
     * Get icon name for this type.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::INFO => 'information-circle',
            self::WARNING => 'exclamation-triangle',
            self::PROMO => 'gift',
            self::URGENT => 'bell-alert',
        };
    }

    /**
     * Get human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::INFO => 'Informativo',
            self::WARNING => 'Advertencia',
            self::PROMO => 'Promoción',
            self::URGENT => 'Urgente',
        };
    }
}
