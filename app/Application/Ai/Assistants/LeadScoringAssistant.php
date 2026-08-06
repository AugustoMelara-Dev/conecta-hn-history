<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class LeadScoringAssistant extends BaseAssistant
{
    public function key(): string { return 'lead_scoring'; }
    public function name(): string { return 'Lead Scoring'; }
    public function description(): string { return 'Calcula score 0-100 para un contacto.'; }
    public function estimateCost(): float { return 0.01; }
    public function category(): string { return 'CRM'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['contact_id'])) {
            return AssistantResult::failure('Missing contact_id');
        }

        return $this->mockOutput($input, [
            'score' => 72,
            'tier' => 'warm',
            'factors' => ['engagement' => 80, 'completeness' => 60, 'recency' => 70],
        ]);
    }
}



