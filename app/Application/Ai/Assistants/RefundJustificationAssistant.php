<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\DTOs\AssistantExecutionContext;

class RefundJustificationAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'refund_justification';
    }

    public function name(): string
    {
        return 'Refund Justification';
    }

    public function description(): string
    {
        return 'Genera una justificación para una solicitud de reembolso.';
    }

    public function estimateCost(): float
    {
        return 0.05;
    }

    public function category(): string
    {
        return 'Billing';
    }

    public function execute(array $input, ?AssistantExecutionContext $context = null): AssistantResult
    {
        if (! isset($input['refund_request_id'])) {
            return AssistantResult::failure('Missing refund_request_id');
        }

        return $this->executeCanonical($input, $context);
    }
}
