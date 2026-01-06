<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * ExportReadyNotification - Export Completion Event
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE: READ-TIME HYDRATION (PERSISTENCE LAYER)                    ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║  Rule 1: Raw Storage. Database stores ONLY file reference & disk.         ║
 * ║  Rule 2: No Dead Links. Never store ephemeral signed URLs in DB.          ║
 * ║  Rule 3: Real-Time Speed. Broadcast gets a temporary valid link for speed.║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */
final class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $filePath,
        private readonly int $tenantId,
        private readonly string $exportType = 'sales',
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification (for database).
     *
     * PERSISTENCE RESTRICTION:
     * - Returns RAW file_path relative to disk root.
     * - Returns disk identifier.
     * - NO signed URLs allowed here.
     *
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'export_ready', // Critical Flag for Hydrator
            'export_type' => $this->exportType,
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'file_path' => $this->filePath, // Raw Reference
            'disk' => 'local',              // Storage Disk
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * PRESENTATION EXCEPTION:
     * - Real-time events are consumed immediately (within ms).
     * - We GENERATE a signed URL here to allow instant access via Toast
     *   without forcing the frontend to query the API immediately.
     *
     * @param mixed $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'success',
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'action_url' => $this->generateSignedDownloadUrl(), // Valid for 30 min (Instant Access)
            'action_label' => 'Descargar',
            'auto_dismiss' => false,
            'duration' => 10000,
        ]);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastType(): string
    {
        return 'export.ready';
    }

    /**
     * Generate a cryptographically signed temporary download URL.
     */
    private function generateSignedDownloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.exports.download',
            now()->addMinutes(30),
            [
                'path' => base64_encode($this->filePath),
                'tenant' => $this->tenantId,
            ]
        );
    }

    private function getTitle(): string
    {
        return match ($this->exportType) {
            'sales' => '📊 Reporte de Ventas Listo',
            'inventory' => '📦 Reporte de Inventario Listo',
            'customers' => '👥 Reporte de Clientes Listo',
            default => '📄 Reporte Listo',
        };
    }

    private function getBody(): string
    {
        return 'El reporte se ha generado exitosamente y está listo para descargar.';
    }
}
