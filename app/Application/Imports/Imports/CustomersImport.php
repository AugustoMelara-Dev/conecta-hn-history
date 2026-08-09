<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\CustomerProfile;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * CustomersImport - Importa customer profiles desde CSV/Excel.
 */
class CustomersImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'customers';
    }

    public function modelClass(): string
    {
        return CustomerProfile::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['email'];
    }

    public function templateHeaders(): array
    {
        return ['name', 'email', 'phone', 'address', 'city', 'country', 'notes'];
    }

    public function model(array $row): ?CustomerProfile
    {
        return CustomerProfile::create([
            'tenant_id' => $this->tenantId,
            'business_id' => $this->tenantId,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'country' => $row['country'] ?? null,
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
