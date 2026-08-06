<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading, WithBatchInserts, Importable
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'products';
    }

    public function modelClass(): string
    {
        return Product::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'sku' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['sku'];
    }

    public function templateHeaders(): array
    {
        return ['name', 'sku', 'price', 'currency', 'stock', 'description', 'is_active'];
    }

    public function model(array $row): ?Product
    {
        return Product::create([
            'tenant_id' => $this->tenantId,
            'name' => $row['name'],
            'sku' => $row['sku'] ?? null,
            'price' => $row['price'],
            'currency' => $row['currency'] ?? 'HNL',
            'stock' => $row['stock'] ?? 0,
            'description' => $row['description'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }
}
