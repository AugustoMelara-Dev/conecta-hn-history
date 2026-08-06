<?php

declare(strict_types=1);

namespace App\Infrastructure\Imports;

use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

/**
 * ExcelImporter - Wrapper sobre Maatwebsite\Excel para procesar archivos grandes.
 *
 * Procesa en chunks de 500 filas, valida, batch-inserts.
 */
class ExcelImporter
{
    public function __construct() {}

    /**
     * Importa un archivo Excel/CSV con una clase ImportToModel.
     *
     * @param  class-string  $importClass  (debe implementar ToModel + WithValidation + WithHeadingRow)
     * @param  string  $filePath  ruta al archivo temporal
     * @return array{success: bool, imported: int, errors: array<int, string>, duration_ms: int}
     */
    public function import(string $importClass, string $filePath): array
    {
        if (! class_exists($importClass)) {
            return ['success' => false, 'imported' => 0, 'errors' => ["Import class not found: {$importClass}"], 'duration_ms' => 0];
        }

        $startTime = microtime(true);

        HeadingRowFormatter::default('none');

        try {
            $import = new $importClass();

            // Validate that required concerns are implemented
            $this->validateConcerns($import);

            \Maatwebsite\Excel\Facades\Excel::import($import, $filePath);

            $imported = method_exists($import, 'getImportedCount') ? $import->getImportedCount() : 0;

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => [],
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            return [
                'success' => false,
                'imported' => 0,
                'errors' => $errors,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'imported' => 0,
                'errors' => [$e->getMessage()],
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    private function validateConcerns(object $import): void
    {
        $required = [WithHeadingRow::class, WithValidation::class];
        foreach ($required as $concern) {
            if (! ($import instanceof $concern)) {
                throw new \InvalidArgumentException("Import class must implement {$concern}");
            }
        }
    }
}
