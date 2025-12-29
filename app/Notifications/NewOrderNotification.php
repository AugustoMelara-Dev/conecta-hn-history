<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * NewOrderNotification - Tenant Alerting System
 *
 * ARCHITECTURE: Asynchronous by Design
 * Implements ShouldQueue to offload email delivery to background workers.
 * Never blocks the HTTP request cycle.
 *
 * DELIVERY CHANNELS:
 * - database: Powers the notification bell in Filament Admin Panel
 * - mail: Transactional email with order summary and CTA
 *
 * TRIGGER: Dispatched after Order::create() transaction commits in OrderController::store()
 */
class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The order instance.
     */
    public Order $order;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * COMPOSITION:
     * - Subject: Clear identification of new order
     * - Body: Order summary (customer, items count, total)
     * - CTA: "View Order" button linking to Filament Resource
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Defensive: Ensure order relationships are loaded
        $this->order->loadMissing(['items', 'tenant']);

        $itemsCount = $this->order->items->count();
        $customerName = $this->order->customer_name ?? 'Cliente';
        $orderNumber = $this->order->order_number;
        $formattedTotal = $this->order->formatted_total;
        $tenantName = $this->order->tenant?->name ?? 'Tu Negocio';

        // Deep link to Filament Order Resource
        $orderUrl = url("/admin/orders/{$this->order->id}/edit");

        return (new MailMessage)
            ->subject("🛒 Nuevo Pedido {$orderNumber} - {$tenantName}")
            ->greeting('¡Nuevo pedido recibido!')
            ->line("**Cliente:** {$customerName}")
            ->line("**Pedido:** {$orderNumber}")
            ->line("**Productos:** {$itemsCount} artículo(s)")
            ->line("**Total:** {$formattedTotal}")
            ->action('Ver Pedido', $orderUrl)
            ->line('Revisa tu panel de administración para gestionar este pedido.')
            ->salutation('— Conecta HN');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * STRUCTURE:
     * - order_id: For quick reference
     * - order_number: Human-readable identifier
     * - formatted_total: Display-ready amount
     * - customer_name: Quick context
     * - items_count: Order size indicator
     * - action_url: Deep link to Filament Resource (notification bell navigation)
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Defensive: Ensure relationships are loaded
        $this->order->loadMissing(['items']);

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'formatted_total' => $this->order->formatted_total,
            'customer_name' => $this->order->customer_name ?? 'Cliente',
            'items_count' => $this->order->items->count(),
            'action_url' => "/admin/orders/{$this->order->id}/edit",
            'message' => "Nuevo pedido {$this->order->order_number} de {$this->order->customer_name} por {$this->order->formatted_total}",
        ];
    }
}
