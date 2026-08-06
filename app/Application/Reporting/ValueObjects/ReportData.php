<?php

declare(strict_types=1);

namespace App\Application\Reporting\ValueObjects;

final readonly class ReportData
{
    /**
     * @param  array<int, \App\Domain\Reporting\ValueObjects\ReportRow>  $rows
     * @param  array<int, \App\Domain\Reporting\ValueObjects\ReportSeries>  $series
     */
    public function __construct(
        public array $rows,
        public array $series,
        public array $summary = [],
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'rows' => array_map(fn ($r) => $r->toArray(), $this->rows),
            'series' => array_map(fn ($s) => $s->toArray(), $this->series),
            'summary' => $this->summary,
            'metadata' => $this->metadata,
        ];
    }
}
