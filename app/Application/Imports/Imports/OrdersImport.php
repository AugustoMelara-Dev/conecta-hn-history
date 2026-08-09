<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Order;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class OrdersImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'orders';
    }

    public function modelClass(): string
    {
        return Order::class;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:50'],
            'customer_email' => ['required', 'email'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:pending,paid,fulfilled,cancelled'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['order_number'];
    }

    public function templateHeaders(): array
    {
        return ['order_number', 'customer_email', 'total_amount', 'currency', 'status'];
    }

    public function model(array $row): ?Order
    {
        return Order::create([
            'tenant_id' => $this->tenantId,
            'order_number' => $row['order_number'],
            'customer_email' => $row['customer_email'],
            'total_amount' => $row['total_amount'],
            'currency' => $row['currency'] ?? 'HNL',
            'status' => $row['status'] ?? 'pending',
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
