<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Order;
use LogicException;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Template compatibility boundary for legacy order spreadsheets.
 *
 * Order aggregates require products, inventory locking, authorization and an
 * idempotency snapshot. A summary-only spreadsheet cannot satisfy that
 * contract, so ingestion fails closed instead of writing partial aggregates.
 */
final class OrdersImport implements Importable, ToModel, WithHeadingRow, WithValidation
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

    public function model(array $row): never
    {
        throw new LogicException(
            'Order spreadsheet ingestion is unavailable because summary rows cannot satisfy the canonical inventory and idempotency contract.'
        );
    }
}
