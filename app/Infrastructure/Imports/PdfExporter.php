<?php

declare(strict_types=1);

namespace App\Infrastructure\Imports;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * PdfExporter - Exporta a PDF usando barryvdh/laravel-dompdf.
 */
class PdfExporter
{
    public function export(string $view, array $data = []): string
    {
        $pdf = Pdf::loadView($view, $data);

        $filename = 'export-'.now()->format('Ymd-His').'.pdf';
        $path = storage_path('app/exports/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    public function download(string $view, array $data = [], string $filename = 'export.pdf'): Response
    {
        $pdf = Pdf::loadView($view, $data);

        return $pdf->download($filename);
    }
}
