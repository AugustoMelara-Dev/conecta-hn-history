<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Contact;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * ContactsImport - Importa contactos desde CSV/Excel.
 */
class ContactsImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $ownerId = null,
    ) {}

    public function entity(): string
    {
        return 'contacts';
    }

    public function modelClass(): string
    {
        return Contact::class;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'lifecycle_stage' => ['nullable', 'in:lead,qualified,proposal,negotiation,won,lost'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['email'];
    }

    public function templateHeaders(): array
    {
        return ['first_name', 'last_name', 'email', 'phone', 'job_title', 'source', 'lifecycle_stage'];
    }

    public function model(array $row): ?Contact
    {
        $data = [
            'tenant_id' => $this->tenantId,
            'first_name' => $row['first_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'job_title' => $row['job_title'] ?? null,
            'source' => $row['source'] ?? null,
            'lifecycle_stage' => $row['lifecycle_stage'] ?? 'lead',
            'owner_id' => $this->ownerId,
        ];

        $contact = new Contact($data);
        $contact->save();

        return $contact;
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
