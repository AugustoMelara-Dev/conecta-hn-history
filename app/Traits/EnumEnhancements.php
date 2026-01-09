<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * EnumEnhancements - Standard Methods for Rich Domain Enums
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Enums as Behavioral Objects                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM: Anemic Enums spread logic everywhere                          ║
 * ║    switch ($status) {                                                   ║
 * ║        case 'in_stock': return 'green';   // In Blade                   ║
 * ║        case 'in_stock': return 'success'; // In Filament                ║
 * ║        case 'in_stock': return true;      // In Controller              ║
 * ║    }                                                                     ║
 * ║    // Same logic duplicated in 10+ places!                              ║
 * ║                                                                          ║
 * ║  SOLUTION: Rich Enums encapsulate their own behavior                    ║
 * ║    $status->color();       // 'success'                                 ║
 * ║    $status->label();       // 'En Stock'                                ║
 * ║    $status->canPurchase(); // true                                      ║
 * ║    // Logic lives in ONE place                                          ║
 * ║                                                                          ║
 * ║  THIS TRAIT PROVIDES:                                                   ║
 * ║    - options(): array for <select> dropdowns                            ║
 * ║    - values(): array for validation rules                               ║
 * ║    - toArray(): array for API serialization                             ║
 * ║                                                                          ║
 * ║  ENUMS MUST IMPLEMENT:                                                  ║
 * ║    - label(): string (human-readable name)                              ║
 * ║    - color(): string (UI color class)                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   enum StockStatus: string {
 *       use EnumEnhancements;
 *       case InStock = 'in_stock';
 *       public function label(): string { ... }
 *       public function color(): string { ... }
 *   }
 */
trait EnumEnhancements
{
    /**
     * Get all enum cases as options array for HTML selects.
     *
     * @return array<string, string> ['value' => 'Label']
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    /**
     * Get all raw values as array (for validation rules).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get validation rule string.
     *
     * Usage: 'status' => ['required', StockStatus::validationRule()]
     */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Serialize to array for API responses.
     *
     * @return array{value: string, label: string, color: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => method_exists($this, 'color') ? $this->color() : 'gray',
            'icon' => method_exists($this, 'icon') ? $this->icon() : null,
        ];
    }

    /**
     * Try to create from value, returning null if invalid.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Create from value or throw descriptive exception.
     */
    public static function fromValue(string $value): self
    {
        $enum = self::tryFrom($value);

        if ($enum === null) {
            $validValues = implode(', ', self::values());
            throw new \InvalidArgumentException(
                "Invalid value '{$value}' for ".self::class.". Valid values: {$validValues}"
            );
        }

        return $enum;
    }
}
