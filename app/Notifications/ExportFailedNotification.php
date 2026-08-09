<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * ExportFailedNotification - User Notification for Failed Export
 *
 * Sent when an export job fails after all retry attempts are exhausted.
 * Provides user-friendly error message without exposing internal details.
 */
final class ExportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $exportType  The type of export that failed
     * @param  string  $errorMessage  User-friendly error message
     */
    public function __construct(
        private readonly string $exportType = 'sales',
        private readonly string $errorMessage = 'No se pudo generar el reporte. Por favor, intenta de nuevo.',
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification (for database).
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'export_failed',
            'export_type' => $this->exportType,
            'title' => $this->getTitle(),
            'body' => $this->errorMessage,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'error',
            'title' => $this->getTitle(),
            'body' => $this->errorMessage,
            'auto_dismiss' => false,
            'duration' => 15000, // 15 seconds for errors
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        // Note: broadcastOn() doesn't receive $notifiable in Laravel 11
        // This method is called during serialization, not per-user
        return [];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastType(): string
    {
        return 'export.failed';
    }

    /**
     * Get notification title based on export type.
     */
    private function getTitle(): string
    {
        return match ($this->exportType) {
            'sales' => '❌ Error en Reporte de Ventas',
            'inventory' => '❌ Error en Reporte de Inventario',
            'customers' => '❌ Error en Reporte de Clientes',
            default => '❌ Error en Reporte',
        };
    }
}
