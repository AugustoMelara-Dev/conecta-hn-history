<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderResource - Standardized API Response Formatting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEPARATION OF CONCERNS: Response structure isolated from controller    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * BENEFITS:
 * - Consistent JSON response structure across all endpoints
 * - Conditional relationship loading (prevents N+1)
 * - Computed fields (formatted_total, whatsapp_url) always available
 * - Easy to extend/modify without touching controller logic
 */
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
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'customer_address' => $this->customer_address,
            'customer_notes' => $this->customer_notes,
            'delivery_type' => $this->delivery_type,
            'payment_method' => $this->payment_method,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'delivery_fee' => $this->delivery_fee,
            'discount_amount' => $this->discount_amount,
            'coupon_code' => $this->coupon_code,
            'total' => $this->total,
            'formatted_total' => $this->formatted_total,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed fields
            'whatsapp_url' => $this->getWhatsAppUrl(),

            // ═══════════════════════════════════════════════════════════════════════
            // PAYMENT INSTRUCTIONS (Server-Side UI Instruction)
            // ═══════════════════════════════════════════════════════════════════════
            // Polymorphic content based on payment method.
            // Frontend renders whatever the backend provides - no hardcoded bank details.
            'payment_instructions' => $this->getPaymentInstructions(),

            // Conditional relationship loading (prevents N+1)
            'items' => $this->whenLoaded('items'),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'slug' => $this->tenant->slug,
                    'whatsapp' => $this->tenant->whatsapp,
                ];
            }),
        ];
    }

    /**
     * Generate payment instructions based on the selected payment method.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  SERVER-SIDE UI: Frontend renders whatever the backend provides         ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * BENEFITS:
     * - No hardcoded bank numbers in React (security)
     * - Easy to update without frontend deploy
     * - Per-tenant customization possible in future
     *
     * @return array|null
     */
    protected function getPaymentInstructions(): ?array
    {
        $paymentMethod = $this->payment_method;

        // Handle both enum instance and raw string
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
                'formatted_amount' => 'L. ' . number_format($this->total, 2),
            ],

            PaymentMethod::Card => null, // Card payments handled by gateway redirect

            PaymentMethod::Pending => [
                'type' => 'pending',
                'title' => 'Método de Pago Pendiente',
                'instruction' => 'Selecciona un método de pago para completar tu orden.',
            ],
        };
    }

    /**
     * Customize the response wrapper.
     *
     * Ensures consistent { success: true, data: {...} } structure.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
