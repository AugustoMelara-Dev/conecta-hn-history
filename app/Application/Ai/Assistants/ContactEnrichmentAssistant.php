<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\DTOs\AssistantExecutionContext;

class ContactEnrichmentAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'contact_enrichment';
    }

    public function name(): string
    {
        return 'Contact Enrichment';
    }

    public function description(): string
    {
        return 'Enriquece datos de contacto con informaci�n p�blica.';
    }

    public function estimateCost(): float
    {
        return 0.02;
    }

    public function category(): string
    {
        return 'CRM';
    }

    public function execute(array $input, ?AssistantExecutionContext $context = null): AssistantResult
    {
        if (! isset($input['identifier']) || trim((string) $input['identifier']) === '') {
            return AssistantResult::failure('Missing contact identifier');
        }

        return $this->executeCanonical($input, $context);
    }
}
