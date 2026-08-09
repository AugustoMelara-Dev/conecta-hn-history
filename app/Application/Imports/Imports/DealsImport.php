<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\Contact;
use App\Models\CrmDeal;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DealsImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $defaultPipelineId,
        private readonly string $defaultStageId,
    ) {}

    public function entity(): string
    {
        return 'deals';
    }

    public function modelClass(): string
    {
        return CrmDeal::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'contact_email' => ['nullable', 'email'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['name', 'contact_email'];
    }

    public function templateHeaders(): array
    {
        return ['name', 'value', 'currency', 'probability', 'expected_close_date', 'contact_email', 'owner_email', 'description'];
    }

    public function model(array $row): ?CrmDeal
    {
        $contactId = null;
        if (! empty($row['contact_email'])) {
            $contact = Contact::where('email', $row['contact_email'])
                ->where('tenant_id', $this->tenantId)
                ->first();
            $contactId = $contact?->id;
        }

        return CrmDeal::create([
            'tenant_id' => $this->tenantId,
            'pipeline_id' => $this->defaultPipelineId,
            'stage_id' => $this->defaultStageId,
            'contact_id' => $contactId,
            'name' => $row['name'],
            'value' => $row['value'] ?? 0,
            'currency' => $row['currency'] ?? 'HNL',
            'probability' => $row['probability'] ?? 10,
            'expected_close_date' => $row['expected_close_date'] ?? null,
            'description' => $row['description'] ?? null,
            'status' => 'open',
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
