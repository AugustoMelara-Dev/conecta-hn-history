<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ReportReadyNotification - Async Report Completion Notification
 *
 * Sent when a background report generation job completes.
 * Includes secure download URL with expiration.
 */
class ReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $reportType,
        public readonly string $downloadUrl,
        public readonly string $tenantName,
        public readonly \DateTimeInterface $expiresAt
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $reportLabel = match ($this->reportType) {
            'sales' => 'Reporte de Ventas',
            'inventory' => 'Reporte de Inventario',
            default => 'Reporte',
        };

        return (new MailMessage)
            ->subject("📊 {$reportLabel} Listo - {$this->tenantName}")
            ->greeting("¡Hola {$notifiable->name}!")
            ->line("Tu **{$reportLabel}** para **{$this->tenantName}** está listo.")
            ->action('Descargar Reporte', $this->downloadUrl)
            ->line("⚠️ Este enlace expira el {$this->expiresAt->format('d/m/Y H:i')}.")
            ->salutation('Dovra AI - Tu plataforma de negocios');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'report_ready',
            'report_type' => $this->reportType,
            'download_url' => $this->downloadUrl,
            'tenant_name' => $this->tenantName,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
