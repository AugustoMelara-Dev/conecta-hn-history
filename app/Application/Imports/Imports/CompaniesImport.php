<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\CrmCompany;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CompaniesImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'companies';
    }

    public function modelClass(): string
    {
        return CrmCompany::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'in:micro,small,medium,large,enterprise'],
            'website' => ['nullable', 'url'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['name'];
    }

    public function templateHeaders(): array
    {
        return ['name', 'industry', 'size', 'website', 'country', 'city', 'phone', 'notes'];
    }

    public function model(array $row): ?CrmCompany
    {
        return CrmCompany::create([
            'tenant_id' => $this->tenantId,
            'name' => $row['name'],
            'industry' => $row['industry'] ?? null,
            'size' => $row['size'] ?? null,
            'website' => $row['website'] ?? null,
            'country' => $row['country'] ?? null,
            'city' => $row['city'] ?? null,
            'phone' => $row['phone'] ?? null,
            'notes' => $row['notes'] ?? null,
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
