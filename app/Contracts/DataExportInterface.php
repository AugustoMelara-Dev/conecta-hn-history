<?php

declare(strict_types=1);

namespace App\Contracts;

interface DataExportInterface
{
    /**
     * Store an export object to a specific disk.
     *
     * @param object $export The export object definition (e.g. implementation of FromQuery)
     * @param string $filePath Relative path on the disk
     * @param string|null $disk Check config if null
     * @return bool True on success
     */
    public function store(object $export, string $filePath, ?string $disk = null): bool;
}
