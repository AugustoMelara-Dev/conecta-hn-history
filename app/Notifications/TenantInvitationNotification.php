<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TenantInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $inviterName,
        public readonly string $role,
        private readonly string $plainTextToken,
        public readonly \DateTimeInterface $expiresAt,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invitación a {$this->tenantName} en Dovra AI")
            ->greeting('Tienes una invitación')
            ->line("{$this->inviterName} te invitó a colaborar en {$this->tenantName}.")
            ->line("Rol propuesto: {$this->role}.")
            ->line('Por seguridad, inicia sesión con el mismo correo que recibió esta invitación.')
            ->action(
                'Revisar invitación',
                route('tenant-invitations.show', ['token' => $this->plainTextToken]),
            )
            ->line('El enlace vence el '.$this->expiresAt->format('Y-m-d H:i T').'.')
            ->salutation('Dovra AI');
    }
}
