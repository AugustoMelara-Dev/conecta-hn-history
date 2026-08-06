<?php

declare(strict_types=1);

namespace App\Infrastructure\Imports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ExcelExporter - Wrapper sobre Maatwebsite\Excel para exportar a xlsx/csv/pdf.
 */
class ExcelExporter
{
    public function export(object $exportClass, string $format = 'xlsx'): string
    {
        $filename = 'export-' . now()->format('Ymd-His') . '.' . $format;
        $tempPath = storage_path('app/exports/' . $filename);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        Excel::store($exportClass, $filename, 'local', $format);

        $actualPath = storage_path('app/' . $filename);
        if (file_exists($actualPath)) {
            return $actualPath;
        }

        return $tempPath;
    }

    public function download(object $exportClass, string $filename = 'export.xlsx', string $format = 'xlsx'): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download($exportClass, $filename, $format);
    }
}
