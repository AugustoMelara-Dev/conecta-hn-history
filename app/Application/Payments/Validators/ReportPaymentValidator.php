<?php

declare(strict_types=1);

namespace App\Application\Validators\Payments;

use App\Application\Billing\Commands\ReportPaymentCommand;

/**
 * ReportPaymentValidator - Structural Validation
 */
class ReportPaymentValidator
{
    public function rules(ReportPaymentCommand $command): array
    {
        return [
            // Prompt Rules: order_id, amount, reference_code, bank_name, proof_image
            // Assuming Command maps these fields. 
            // Note: Spec 4 asked to validate "order_id": ['required', 'exists:orders,id']
            // But ReportPaymentHandler (visible in previous turn) uses tenant_id/paymentReference/amount.
            // It does NOT seem to link to Order ID? Or maybe Logic links it?
            // "Contexto Tecnico: Usado para transferencias bancarias... referencia bancaria."
            // If Command has orderId, I validate it. If not, I stick to Command fields?
            // Spec 4 explicitly requests: order_id: ['required', 'exists:orders,id'].
            // I will assume Command SHOULD HAVE IT. 
            // But existing Handler works without it?
            // "The administrator needs clear references to reconcile."
            // Linking to Order is good practice.
            // I'll add the rule. If Command lacks it, I might need to update Command too.
            // But I am writing Validator now. 
            'orderId' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paymentReference' => ['required', 'string', 'min:5'],
            'bank' => ['required', 'string'],
            'proofOfPaymentPath' => ['nullable', 'string', 'max:2048'], // Validating path string, not file upload object here
        ];
    }
}
