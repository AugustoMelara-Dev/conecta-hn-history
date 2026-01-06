<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * OrderStatusChangedNotification - Customer Feedback Loop
 *
 * ARCHITECTURE: Asynchronous by Design
 * Implements ShouldQueue to offload email delivery to background workers.
 * Never blocks the HTTP request cycle.
 *
 * INTELLIGENCE:
 * Maps internal status codes (processing, ready, delivered) to
 * human-readable, friendly messages in Spanish.
 *
 * DEFENSIVE CODING:
 * Gracefully aborts if customer_email is null/empty to prevent
 * queue worker exceptions.
 *
 * TRIGGER: Dispatched from Order::transitionTo() method
 */
class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The order instance.
     */
    public Order $order;

    /**
     * The previous status (for context).
     */
    public string $previousStatus;

    /**
     * The new status.
     */
    public string $newStatus;

    /**
     * Status labels in Spanish (human-readable).
     */
    protected static array $statusLabels = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'processing' => 'En Preparación',
        'ready' => 'Listo para Recoger',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
    ];

    /**
     * Status messages for email body.
     */
    protected static array $statusMessages = [
        'confirmed' => '¡Buenas noticias! Tu pedido ha sido confirmado y será procesado pronto.',
        'processing' => '¡Tu pedido está siendo preparado! Nuestro equipo está trabajando en él.',
        'ready' => '🎉 ¡Tu pedido está listo! Puedes pasar a recogerlo cuando gustes.',
        'delivered' => '✅ Tu pedido ha sido entregado. ¡Gracias por tu preferencia!',
        'cancelled' => 'Lamentamos informarte que tu pedido ha sido cancelado. Contáctanos si tienes dudas.',
    ];

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, string $previousStatus, string $newStatus)
    {
        $this->order = $order;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Determine if the notification should be sent.
     *
     * DEFENSIVE GUARD:
     * Aborts notification if customer_email is null or empty.
     * Logs the skip for debugging purposes.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $customerEmail = $this->order->customer_email;

        if (empty($customerEmail)) {
            Log::info('OrderStatusChangedNotification skipped: No customer email', [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'new_status' => $this->newStatus,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Get the notification's delivery channels.
     *
     * Only mail channel - this goes to the customer, not the tenant.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * COMPOSITION:
     * - Subject: Status-specific, friendly message
     * - Body: Human-readable status update with context
     * - CTA: None (purely informational)
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Defensive: Load tenant for branding
        $this->order->loadMissing(['tenant']);

        $orderNumber = $this->order->order_number;
        $tenantName = $this->order->tenant?->name ?? 'Tu Negocio';
        $statusLabel = self::$statusLabels[$this->newStatus] ?? ucfirst($this->newStatus);
        $statusMessage = self::$statusMessages[$this->newStatus] ?? "Tu pedido ha sido actualizado a: {$statusLabel}";

        // Build subject based on status
        $subject = match ($this->newStatus) {
            'confirmed' => "✅ Pedido {$orderNumber} Confirmado",
            'processing' => "👨‍🍳 Pedido {$orderNumber} en Preparación",
            'ready' => "🎉 Pedido {$orderNumber} ¡Listo!",
            'delivered' => "📦 Pedido {$orderNumber} Entregado",
            'cancelled' => "❌ Pedido {$orderNumber} Cancelado",
            default => "📋 Actualización del Pedido {$orderNumber}",
        };

        $mail = (new MailMessage)
            ->subject("{$subject} - {$tenantName}")
            ->greeting("Hola, {$this->order->customer_name}!")
            ->line($statusMessage)
            ->line("**Pedido:** {$orderNumber}")
            ->line("**Estado:** {$statusLabel}")
            ->line("**Total:** {$this->order->formatted_total}");

        // Add cancel reason if applicable
        if ($this->newStatus === 'cancelled' && $this->order->cancel_reason) {
            $mail->line("**Motivo:** {$this->order->cancel_reason}");
        }

        $mail->salutation("Gracias por tu preferencia — {$tenantName}");

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'status_label' => self::$statusLabels[$this->newStatus] ?? ucfirst($this->newStatus),
        ];
    }
}
