<?php

namespace App\Mail\Security;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $ipAddress;

    public $time;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $ipAddress, $time)
    {
        $this->user = $user;
        $this->ipAddress = $ipAddress;
        $this->time = $time;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Alerta de Seguridad: Nuevo Inicio de Sesión',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <h1>Alerta de Seguridad</h1>
                <p>Hola {$this->user->name},</p>
                <p>Se ha detectado un nuevo inicio de sesión en tu cuenta de Conecta HN.</p>
                <ul>
                    <li><strong>Fecha y Hora:</strong> {$this->time->format('d/m/Y H:i:s')}</li>
                    <li><strong>Dirección IP:</strong> {$this->ipAddress}</li>
                </ul>
                <p>Si no fuiste tú, por favor cambia tu contraseña inmediatamente.</p>
                <p>Atentamente,<br>El equipo de seguridad de Conecta HN</p>
            "
        );
    }
}
