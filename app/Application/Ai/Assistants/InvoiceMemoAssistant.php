<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class InvoiceMemoAssistant extends BaseAssistant
{
    public function key(): string { return 'invoice_memo'; }
    public function name(): string { return 'Invoice Collection Memo'; }
    public function description(): string { return 'Memo de cobranza para invoice.'; }
    public function estimateCost(): float { return 0.04; }
    public function category(): string { return 'Billing'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['invoice_id'])) {
            return AssistantResult::failure('Missing invoice_id');
        }
        return $this->mockOutput($input, [
            'memo_subject' => 'Recordatorio de pago',
            'memo_body' => "Estimado cliente, le recordamos que su factura est� pr�xima a vencer...",
            'tone' => 'polite_firm',
        ]);
    }
}
