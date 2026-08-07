<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class RefundJustificationAssistant extends BaseAssistant
{
    public function key(): string { return 'refund_justification'; }
    public function name(): string { return 'Refund Justification'; }
    public function description(): string { return 'Justificaci�n para refund request.'; }
    public function estimateCost(): float { return 0.05; }
    public function category(): string { return 'Billing'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['refund_request_id'])) {
            return AssistantResult::failure('Missing refund_request_id');
        }
        return $this->mockOutput($input, [
            'justification' => 'Refund aprobado por cumplimiento de pol�tica.',
            'risk_score' => 'low',
        ]);
    }
}
