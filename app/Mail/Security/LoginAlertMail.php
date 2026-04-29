<?php

declare(strict_types=1);

namespace App\Mail\Security;

use App\Application\Shared\DTOs\Mail\UserMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * LoginAlertMail - Refactored for Safe Serialization
 *
 * Uses UserMailData DTO and primitive login context instead of Eloquent Model.
 */
class LoginAlertMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     *
     * @param  UserMailData  $user  User snapshot at dispatch time
     * @param  string  $ipAddress  IP address of login attempt
     * @param  string  $time  Formatted timestamp of login attempt
     */
    public function __construct(
        public readonly UserMailData $user,
        public readonly string $ipAddress,
        public readonly string $time
    ) {}

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
                <p>Se ha detectado un nuevo inicio de sesión en tu cuenta de Vito Business OS.</p>
                <ul>
                    <li><strong>Fecha y Hora:</strong> {$this->time}</li>
                    <li><strong>Dirección IP:</strong> {$this->ipAddress}</li>
                </ul>
                <p>Si no fuiste tú, por favor cambia tu contraseña inmediatamente.</p>
                <p>Atentamente,<br>El equipo de seguridad de Vito Business OS</p>
            "
        );
    }
}
