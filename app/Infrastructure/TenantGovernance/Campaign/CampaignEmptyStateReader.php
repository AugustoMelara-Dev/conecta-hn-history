<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Campaign;

final class CampaignEmptyStateReader
{
    /**
     * @return array{badge:string,heading:string,description:string,buttonLabel:string}
     */
    public function read(): array
    {
        return [
            'badge' => 'Marketing PRO',
            'heading' => 'Todavía no has creado campañas',
            'description' => 'Crea campañas por segmento, tier, reward, sede y owner usando la base real de tu CRM.',
            'buttonLabel' => 'Crear campana',
        ];
    }
}
