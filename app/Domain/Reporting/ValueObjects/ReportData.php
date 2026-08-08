<?php
declare(strict_types=1);
namespace App\Domain\Reporting\ValueObjects;

use App\Domain\Reporting\ValueObjects\ReportRow;
use App\Domain\Reporting\ValueObjects\ReportSeries;

final readonly class ReportData
{
    /**
     * @param  array<int, ReportRow>  $rows
     * @param  array<int, ReportSeries>  $series
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
