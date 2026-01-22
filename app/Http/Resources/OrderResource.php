<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Order',
    title: 'Order Resource',
    description: 'Transaction details',
    readOnly: true,
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1001),
        new OA\Property(property: 'code', type: 'string', format: 'uuid', description: 'Tracking UUID', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'status_label', type: 'string', example: 'Completada'),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 150.00),
        new OA\Property(property: 'formatted_total', type: 'string', example: 'L. 150.00'),
        new OA\Property(property: 'items_count', type: 'integer', example: 3),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
        new OA\Property(property: 'payment_instructions', type: 'object', nullable: true, description: 'Dynamic limits for UI rendering'),
    ]
)]
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->tracking_uuid,
            'status_label' => $this->status_label,
            'total' => (float) $this->total,
            'formatted_total' => $this->formatted_total,
            'items_count' => $this->items->count(),

            // Conditional Relations
            'items' => $this->whenLoaded('items'),

            // Keep payment instructions as they are UI critical
            'payment_instructions' => $this->getPaymentInstructions(),
        ];
    }

    /**
     * Generate payment instructions based on the selected payment method.
     */
    protected function getPaymentInstructions(): ?array
    {
        $paymentMethod = $this->payment_method;

        if ($paymentMethod instanceof PaymentMethod) {
            $method = $paymentMethod;
        } elseif (is_string($paymentMethod)) {
            $method = PaymentMethod::tryFrom($paymentMethod);
        } else {
            $method = null;
        }

        if (! $method) {
            return null;
        }

        return match ($method) {
            PaymentMethod::BankTransfer => [
                'type' => 'bank_transfer',
                'title' => 'Datos para Transferencia',
                'bank_name' => $this->tenant?->bank_name ?? 'BAC Credomatic',
                'account_number' => $this->tenant?->bank_account ?? '000-000000-0',
                'account_name' => $this->tenant?->bank_account_name ?? $this->tenant?->name ?? 'Nombre del Negocio',
                'account_type' => 'Ahorro',
                'call_to_action' => 'Envía una captura de tu comprobante por WhatsApp',
                'whatsapp_message' => "Hola! Acabo de realizar la transferencia para el pedido #{$this->order_number}. Adjunto comprobante.",
            ],
            PaymentMethod::Cash => [
                'type' => 'cash',
                'title' => 'Pago en Efectivo',
                'instruction' => 'Por favor ten el cambio exacto listo para el repartidor.',
                'amount_due' => $this->total,
                'formatted_amount' => 'L. '.number_format((float) $this->total, 2),
            ],
            PaymentMethod::Card => null,
            PaymentMethod::Pending => [
                'type' => 'pending',
                'title' => 'Método de Pago Pendiente',
                'instruction' => 'Selecciona un método de pago para completar tu orden.',
            ],
        };
    }
}
