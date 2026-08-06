<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

final readonly class ReportRow
{
    public function __construct(
        public string $label,
        public float|int|string $value,
        public ?float $previousValue = null,
        public ?string $unit = null,
        public array $metadata = [],
    ) {}

    public function change(): ?float
    {
        if ($this->previousValue === null || $this->previousValue == 0) {
            return null;
        }
        return (($this->value - $this->previousValue) / abs($this->previousValue)) * 100;
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'previous_value' => $this->previousValue,
            'change_pct' => $this->change(),
            'unit' => $this->unit,
            'metadata' => $this->metadata,
        ];
    }
}
