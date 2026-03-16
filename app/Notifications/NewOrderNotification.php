<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * NewOrderNotification - Tenant Alerting System
 *
 * ARCHITECTURE: Asynchronous by Design
 * Implements ShouldQueue to offload email delivery to background workers.
 * Implements ShouldBroadcast for real-time WebSocket notifications via Reverb.
 * Never blocks the HTTP request cycle.
 *
 * DELIVERY CHANNELS:
 * - database: Powers the notification bell in Filament Admin Panel
 * - mail: Transactional email with order summary and CTA
 * - broadcast: Real-time push to tenant admins via WebSocket
 *
 * TRIGGER: Dispatched after Order::create() transaction commits in OrderController::store()
 */
class NewOrderNotification extends Notification implements ShouldQueue, ShouldBroadcast
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
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Get the channels the notification should broadcast on.
     *
     * SECURITY: Broadcasts to tenant-scoped admin channel only.
     * Maps to ChannelRegistry::TENANT_ADMIN_CHANNEL_PATTERN.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->order->tenant_id . '.admins'),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * PERFORMANCE: Lightweight payload with IDs only, no full models.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number ?? 'N/A',
            'amount' => $this->order->total ?? 0,
            'tenant_id' => $this->order->tenant_id,
            'customer_name' => $this->order->customer_name ?? 'Cliente',
            'created_at' => $this->order->created_at?->toIso8601String(),
        ]);
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
        $formattedTotal = 'L. ' . number_format((float) ($this->order->total ?? 0), 2);
        $tenantName = $this->order->tenant?->name ?? 'Tu Negocio';

        // Deep link to Filament Order Resource in the App Panel
        $orderUrl = url("/app/orders/{$this->order->id}");

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

        $formattedTotal = 'L. ' . number_format((float) ($this->order->total ?? 0), 2);

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'formatted_total' => $formattedTotal,
            'customer_name' => $this->order->customer_name ?? 'Cliente',
            'items_count' => $this->order->items->count(),
            'action_url' => "/app/orders/{$this->order->id}",
            'message' => "Nuevo pedido {$this->order->order_number} de {$this->order->customer_name} por {$formattedTotal}",
        ];
    }
}
