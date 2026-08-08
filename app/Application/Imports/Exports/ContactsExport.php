<?php

declare(strict_types=1);

namespace App\Application\Imports\Exports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ContactsExport - Exporta contactos a Excel/CSV/PDF.
 */
class ContactsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly string $tenantId,
        private readonly array $filters = [],
    ) {}

    public function query()
    {
        $query = Contact::query()->where('tenant_id', $this->tenantId);

        if (isset($this->filters['lifecycle_stage'])) {
            $query->where('lifecycle_stage', $this->filters['lifecycle_stage']);
        }
        if (isset($this->filters['owner_id'])) {
            $query->where('owner_id', $this->filters['owner_id']);
        }

        return $query;
    }

    public function headings(): array
    {
        return ['ID', 'Nombre', 'Email', 'Teléfono', 'Etapa', 'Fuente', 'Último contacto', 'Created'];
    }

    public function map($contact): array
    {
        return [
            $contact->id,
            $contact->full_name,
            $contact->email,
            $contact->phone,
            $contact->lifecycle_stage,
            $contact->source,
            $contact->last_contact_at?->format('Y-m-d H:i'),
            $contact->created_at?->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['type' => 'solid', 'color' => ['rgb' => '4F46E5']]],
        ];
    }
}
