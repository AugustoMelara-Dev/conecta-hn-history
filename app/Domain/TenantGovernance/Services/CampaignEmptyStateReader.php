<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class CampaignEmptyStateReader
{
    /**
     * @return array{badge:string,heading:string,description:string,buttonLabel:string}
     */
    public function read(): array
    {
        return [
            'badge' => 'Marketing PRO',
            'heading' => 'Todavia no has creado campanas',
            'description' => 'Crea campanas por segmento, tier, reward, sede y owner usando la base real de tu CRM.',
            'buttonLabel' => 'Crear campana',
        ];
    }
}
