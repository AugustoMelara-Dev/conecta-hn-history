<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SubscriptionExpiringNotification - High-Priority Retention Signal
 *
 * ARCHITECTURE: Asynchronous by Design
 * Implements ShouldQueue to offload email delivery to background workers.
 * Never blocks the HTTP request cycle.
 *
 * DELIVERY CHANNELS:
 * - database: Powers notification bell in Filament Admin Panel
 * - mail: Urgency-driven but professional email warning
 *
 * CONTENT STRATEGY:
 * - 72-hour warning (professional but urgent)
 * - Direct CTA to billing page to minimize renewal friction
 * - Clear value proposition reminder
 *
 * TRIGGER: Dispatched by CheckSubscriptionExpiry console command (daily at 08:00)
 */
class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The tenant whose subscription is expiring.
     */
    public Tenant $tenant;

    /**
     * Create a new notification instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
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
     * - Subject: Urgency-driven (72 hours warning)
     * - Body: Professional, value-focused, actionable
     * - CTA: Direct link to authenticated billing page
     */
    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->tenant->name;
        $expiryDate = $this->tenant->subscription_ends_at?->format('d/m/Y') ?? 'pronto';
        $planLabel = $this->tenant->plan_type_label ?? 'PRO';

        // Direct authenticated link to billing page
        $billingUrl = url('/admin/billing');

        return (new MailMessage)
            ->subject("⚠️ Tu suscripción {$planLabel} expira en 72 horas")
            ->greeting('Hola!')
            ->line("Tu suscripción **{$planLabel}** para **{$tenantName}** expira el **{$expiryDate}**.")
            ->line('Para evitar la interrupción de tus servicios PRO, te recomendamos renovar ahora.')
            ->line('**Beneficios que perderás:**')
            ->line('• Productos ilimitados en tu menú')
            ->line('• Sistema de pedidos avanzado')
            ->line('• Cupones y promociones')
            ->line('• Analíticas detalladas')
            ->line('• Prioridad en el directorio')
            ->action('🔄 Renovar Ahora', $billingUrl)
            ->line('Si tienes alguna pregunta sobre tu suscripción, contáctanos.')
            ->salutation('— El equipo de Conecta HN');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * STRUCTURE:
     * Powers the notification bell in Filament with actionable data.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'plan_type' => $this->tenant->plan_type,
            'subscription_ends_at' => $this->tenant->subscription_ends_at?->toIso8601String(),
            'action_url' => '/admin/billing',
            'message' => "⚠️ Tu suscripción para {$this->tenant->name} expira en 72 horas. Renueva ahora para mantener tus beneficios PRO.",
            'urgency' => 'high',
        ];
    }
}
