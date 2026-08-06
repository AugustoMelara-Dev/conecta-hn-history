<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

final readonly class ReportSeries
{
    /**
     * @param  array<int, string>  $labels
     * @param  array<int, float|int>  $values
     */
    public function __construct(
        public string $name,
        public array $labels,
        public array $values,
        public string $type = 'line',
        public ?string $color = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'labels' => $this->labels,
            'values' => $this->values,
            'type' => $this->type,
            'color' => $this->color,
        ];
    }
}
