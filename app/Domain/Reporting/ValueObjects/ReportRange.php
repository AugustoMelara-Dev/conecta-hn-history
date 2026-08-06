<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * ReportRange - Rango de fechas para queries de reportes.
 *
 * Soporta presets (today/yesterday/7d/30d/qtd/ytd) o custom.
 * Cada rango tiene comparison_start/comparison_end para análisis vs período previo.
 */
final readonly class ReportRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ?CarbonImmutable $comparisonStart = null,
        public ?CarbonImmutable $comparisonEnd = null,
    ) {}

    public static function today(): self
    {
        $start = CarbonImmutable::today();
        $end = CarbonImmutable::today()->endOfDay();
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subDay(),
            comparisonEnd: $end->subDay(),
        );
    }

    public static function yesterday(): self
    {
        $start = CarbonImmutable::yesterday();
        $end = CarbonImmutable::yesterday()->endOfDay();
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subDay(),
            comparisonEnd: $end->subDay(),
        );
    }

    public static function last7Days(): self
    {
        $start = CarbonImmutable::now()->subDays(7)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subDays(7),
            comparisonEnd: $end->subDays(7),
        );
    }

    public static function last30Days(): self
    {
        $start = CarbonImmutable::now()->subDays(30)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subDays(30),
            comparisonEnd: $end->subDays(30),
        );
    }

    public static function quarterToDate(): self
    {
        $start = CarbonImmutable::now()->startOfQuarter();
        $end = CarbonImmutable::now()->endOfDay();
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subQuarter(),
            comparisonEnd: $start->subSecond(),
        );
    }

    public static function yearToDate(): self
    {
        $start = CarbonImmutable::now()->startOfYear();
        $end = CarbonImmutable::now()->endOfDay();
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subYear(),
            comparisonEnd: $start->subSecond(),
        );
    }

    public static function custom(CarbonImmutable $start, CarbonImmutable $end): self
    {
        $duration = $end->diffInDays($start);
        return new self(
            start: $start,
            end: $end,
            comparisonStart: $start->subDays($duration),
            comparisonEnd: $end->subDays($duration),
        );
    }

    public static function fromPreset(string $preset): self
    {
        return match ($preset) {
            'today' => self::today(),
            'yesterday' => self::yesterday(),
            '7d' => self::last7Days(),
            '30d' => self::last30Days(),
            'qtd' => self::quarterToDate(),
            'ytd' => self::yearToDate(),
            default => self::last30Days(),
        };
    }

    public function days(): int
    {
        return $this->start->diffInDays($this->end);
    }

    public function hasComparison(): bool
    {
        return $this->comparisonStart !== null && $this->comparisonEnd !== null;
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'comparison_start' => $this->comparisonStart?->toIso8601String(),
            'comparison_end' => $this->comparisonEnd?->toIso8601String(),
            'days' => $this->days(),
        ];
    }
}
