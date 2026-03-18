<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
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
/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'id' => $order->id,
            'code' => $order->tracking_uuid,
            'status_label' => $order->status_label,
            'total' => (float) $order->total,
            'formatted_total' => $order->formatted_total,
            'items_count' => $order->items->count(),
            'items' => $this->whenLoaded('items'),
            'payment_instructions' => $this->getPaymentInstructions($order),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getPaymentInstructions(Order $order): ?array
    {
        return match ($order->payment_method) {
            'transfer' => [
                'type' => 'bank_transfer',
                'title' => 'Datos para Transferencia',
                'bank_name' => $order->tenant->bank_name ?? 'BAC Credomatic',
                'account_number' => $order->tenant->bank_account ?? '000-000000-0',
                'account_name' => $order->tenant->bank_account_name ?? $order->tenant->name ?? 'Nombre del Negocio',
                'account_type' => 'Ahorro',
                'call_to_action' => 'Envia una captura de tu comprobante por WhatsApp',
                'whatsapp_message' => "Hola! Acabo de realizar la transferencia para el pedido #{$order->order_number}. Adjunto comprobante.",
            ],
            'cash' => [
                'type' => 'cash',
                'title' => 'Pago en Efectivo',
                'instruction' => 'Por favor ten el cambio exacto listo para el repartidor.',
                'amount_due' => $order->total,
                'formatted_amount' => 'L. '.number_format((float) $order->total, 2),
            ],
            'card' => null,
            'pending' => [
                'type' => 'pending',
                'title' => 'Metodo de Pago Pendiente',
                'instruction' => 'Selecciona un metodo de pago para completar tu orden.',
            ],
        };
    }
}
