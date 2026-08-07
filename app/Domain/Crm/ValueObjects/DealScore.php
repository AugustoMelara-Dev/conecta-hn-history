<?php

declare(strict_types=1);

namespace App\Domain\Crm\ValueObjects;

/**
 * DealScore - Score de calidad del deal (0-100).
 *
 * Calculado por LeadScoringService basándose en:
 * - Lifecycle stage
 * - Engagement (actividades, emails abiertos, calls)
 * - Deal value vs tenant median
 * - Time to close
 */
final readonly class DealScore
{
    public function __construct(
        public int $value,
        public string $tier,
        public array $factors,
    ) {
        if ($value < 0 || $value > 100) {
            throw new \InvalidArgumentException('Deal score must be between 0 and 100');
        }
    }

    public static function fromFactors(int $value, array $factors): self
    {
        $tier = match (true) {
            $value >= 80 => 'hot',
            $value >= 60 => 'warm',
            $value >= 40 => 'qualified',
            $value >= 20 => 'cold',
            default => 'unqualified',
        };

        return new self($value, $tier, $factors);
    }

    public function isHot(): bool
    {
        return $this->value >= 80;
    }

    public function isWarm(): bool
    {
        return $this->value >= 60 && $this->value < 80;
    }

    public function color(): string
    {
        return match ($this->tier) {
            'hot' => 'danger',
            'warm' => 'warning',
            'qualified' => 'info',
            'cold' => 'gray',
            'unqualified' => 'gray',
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'tier' => $this->tier,
            'factors' => $this->factors,
            'color' => $this->color(),
        ];
    }
}
