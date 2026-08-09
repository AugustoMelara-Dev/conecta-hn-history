<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class NextBestActionAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'next_best_action';
    }

    public function name(): string
    {
        return 'Next Best Action';
    }

    public function description(): string
    {
        return 'Recomienda pr�xima acci�n para un contacto.';
    }

    public function estimateCost(): float
    {
        return 0.05;
    }

    public function category(): string
    {
        return 'CRM';
    }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['contact_id'])) {
            return AssistantResult::failure('Missing contact_id');
        }

        return $this->mockOutput($input, [
            'recommended_action' => 'schedule_call',
            'reason' => 'Contacto activo hace 14 d�as, alta probabilidad de cierre.',
            'priority' => 'high',
        ]);
    }
}
