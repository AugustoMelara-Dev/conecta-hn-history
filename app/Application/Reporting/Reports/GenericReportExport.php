<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * GenericReportExport - Export genérico a Excel/CSV.
 *
 * Adapter que convierte ReportData a FromArray.
 */
class GenericReportExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly ReportInterface $report,
        private readonly string $tenantId,
        private readonly ReportRange $range,
    ) {}

    public function headings(): array
    {
        return $this->report->getColumns();
    }

    public function array(): array
    {
        $data = $this->report->getData($this->tenantId, $this->range);

        return array_map(
            fn ($row) => [
                $row->label,
                $row->value,
                $row->previousValue ?? '',
                $row->change() ? round($row->change(), 2).'%' : '',
                $row->unit ?? '',
            ],
            $data->rows,
        );
    }
}
