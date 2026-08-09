<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Coupon;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CouponsImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'coupons';
    }

    public function modelClass(): string
    {
        return Coupon::class;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['code'];
    }

    public function templateHeaders(): array
    {
        return ['code', 'discount_type', 'discount_value', 'expires_at', 'usage_limit'];
    }

    public function model(array $row): ?Coupon
    {
        return Coupon::create([
            'tenant_id' => $this->tenantId,
            'code' => $row['code'],
            'discount_type' => $row['discount_type'],
            'discount_value' => $row['discount_value'],
            'expires_at' => $row['expires_at'] ?? null,
            'usage_limit' => $row['usage_limit'] ?? null,
            'is_active' => true,
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
