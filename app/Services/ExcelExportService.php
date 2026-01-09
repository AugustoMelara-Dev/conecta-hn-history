<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DataExportInterface;
use Maatwebsite\Excel\Facades\Excel;

final class ExcelExportService implements DataExportInterface
{
    public function store(object $export, string $filePath, ?string $disk = null): bool
    {
        // Use configured disk if none provided
        $disk = $disk ?? config('filesystems.default_export_disk', 'local');

        return Excel::store($export, $filePath, $disk);
    }
}
