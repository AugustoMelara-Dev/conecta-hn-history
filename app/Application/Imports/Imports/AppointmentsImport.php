<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Appointment;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AppointmentsImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'appointments';
    }

    public function modelClass(): string
    {
        return Appointment::class;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'customer_email' => ['nullable', 'email'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['title', 'starts_at'];
    }

    public function templateHeaders(): array
    {
        return ['title', 'customer_email', 'starts_at', 'ends_at', 'notes'];
    }

    public function model(array $row): ?Appointment
    {
        return Appointment::create([
            'tenant_id' => $this->tenantId,
            'title' => $row['title'],
            'customer_email' => $row['customer_email'] ?? null,
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'notes' => $row['notes'] ?? null,
            'status' => 'pending',
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
