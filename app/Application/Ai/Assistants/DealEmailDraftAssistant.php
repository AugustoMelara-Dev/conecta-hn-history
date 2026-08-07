<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

/**
 * DealEmailDraftAssistant - Genera draft de email para un deal.
 */
class DealEmailDraftAssistant extends BaseAssistant
{
    public function key(): string { return 'deal_email_draft'; }
    public function name(): string { return 'Deal Email Draft'; }
    public function description(): string { return 'Genera un draft de email contextual para un deal espec�fico.'; }
    public function estimateCost(): float { return 0.05; }
    public function category(): string { return 'CRM'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['deal_id'])) {
            return AssistantResult::failure('Missing deal_id in input');
        }

        return $this->mockOutput($input, [
            'email_subject' => 'Seguimiento a tu inter�s en nuestros servicios',
            'email_body' => "Hola,\n\nGracias por tu inter�s. Adjunto los detalles relevantes...",
            'tone' => 'professional',
            'language' => 'es',
        ]);
    }
}
