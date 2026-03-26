<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class CustomerProfileEmptyStateReader
{
    /**
     * @return array{badge:string,heading:string,description:string}
     */
    public function read(): array
    {
        return [
            'badge' => 'CRM',
            'heading' => 'Todavia no hay clientes consolidados',
            'description' => 'Cuando entren pedidos, citas o prospectos con contacto, el CRM armara la ficha automaticamente.',
        ];
    }
}
