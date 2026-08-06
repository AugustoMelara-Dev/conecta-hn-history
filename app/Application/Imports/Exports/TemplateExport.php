<?php

declare(strict_types=1);

namespace App\Application\Imports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * TemplateExport - Genera un template con headers y una fila de ejemplo.
 */
class TemplateExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $headers,
        private readonly array $sampleRow,
    ) {}

    public function headings(): array
    {
        return $this->headers;
    }

    public function array(): array
    {
        return [$this->sampleRow];
    }
}
