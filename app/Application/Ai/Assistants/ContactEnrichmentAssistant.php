<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class ContactEnrichmentAssistant extends BaseAssistant
{
    public function key(): string { return 'contact_enrichment'; }
    public function name(): string { return 'Contact Enrichment'; }
    public function description(): string { return 'Enriquece datos de contacto con informaci�n p�blica.'; }
    public function estimateCost(): float { return 0.02; }
    public function category(): string { return 'CRM'; }

    public function execute(array $input): AssistantResult
    {
        return $this->mockOutput($input, [
            'job_title' => 'Director',
            'company_name' => 'Empresa Detectada SA',
            'linkedin_url' => null,
            'seniority' => 'senior',
            'industry' => 'tecnologia',
            'confidence' => 0.82,
        ]);
    }
}
