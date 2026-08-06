<?php

declare(strict_types=1);

namespace App\Application\Imports\Exports;

use App\Models\CustomerProfile;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomersExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly string $tenantId,
        private readonly array $filters = [],
    ) {}

    public function query()
    {
        $query = CustomerProfile::query()->where('tenant_id', $this->tenantId);
        if (isset($this->filters['city'])) {
            $query->where('city', $this->filters['city']);
        }
        return $query;
    }

    public function headings(): array
    {
        return ['ID', 'Nombre', 'Email', 'Teléfono', 'Ciudad', 'País'];
    }

    public function map($customer): array
    {
        return [
            $customer->id,
            $customer->name,
            $customer->email,
            $customer->phone,
            $customer->city,
            $customer->country,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['type' => 'solid', 'color' => ['rgb' => '4F46E5']]],
        ];
    }
}
