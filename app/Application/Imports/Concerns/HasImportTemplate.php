<?php

declare(strict_types=1);

namespace App\Application\Imports\Concerns;

/**
 * HasImportTemplate - Trait con defaults para templates.
 */
trait HasImportTemplate
{
    abstract public function templateHeaders(): array;

    public function templateSampleRow(): array
    {
        $row = [];
        foreach ($this->templateHeaders() as $header) {
            $row[$header] = match (strtolower($header)) {
                'name', 'first_name', 'last_name' => 'Juan Pérez',
                'email' => 'juan@example.com',
                'phone' => '+504 9999 9999',
                'price', 'value' => 100.00,
                'quantity', 'stock' => 10,
                'is_active', 'active' => true,
                'description', 'notes', 'body' => 'Descripción de ejemplo',
                'currency' => 'HNL',
                'country' => 'HN',
                default => 'valor',
            };
        }
        return $row;
    }
}
