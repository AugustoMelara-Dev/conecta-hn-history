<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportKey;
use App\Infrastructure\Imports\ExcelExporter;
use App\Infrastructure\Imports\PdfExporter;

/**
 * ReportExporter - Exporta un Report a PDF/XLSX/CSV.
 */
class ReportExporter
{
    public function __construct(
        private readonly ExcelExporter $excelExporter,
        private readonly PdfExporter $pdfExporter,
    ) {}

    public function export(
        ReportInterface $report,
        string $tenantId,
        \App\Domain\Reporting\ValueObjects\ReportRange $range,
        ReportFormat $format = ReportFormat::Pdf,
    ): string {
        $data = $report->getData($tenantId, $range);

        return match ($format) {
            ReportFormat::Pdf => $this->pdfExporter->export(
                'reports.generic',
                [
                    'report' => $report,
                    'data' => $data->toArray(),
                    'range' => $range->toArray(),
                ],
            ),
            ReportFormat::Xlsx => $this->excelExporter->export(
                new \App\Application\Reporting\Reports\GenericReportExport($report, $tenantId, $range),
            ),
            ReportFormat::Csv => $this->writeCsv($report, $data),
        };
    }

    private function writeCsv(ReportInterface $report, ReportData $data): string
    {
        $filename = 'report-' . $report->getKey()->value . '-' . now()->format('Ymd-His') . '.csv';
        $path = storage_path('app/reports/' . $filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');
        fputcsv($file, $report->getColumns());

        foreach ($data->rows as $row) {
            fputcsv($file, [
                $row->label,
                $row->value,
                $row->previousValue ?? '',
                $row->change() ?? '',
                $row->unit ?? '',
            ]);
        }

        fclose($file);

        return $path;
    }
}
