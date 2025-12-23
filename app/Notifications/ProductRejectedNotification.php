<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Product $product
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Producto Rechazado - '.$this->product->name)
            ->greeting('Hola '.$notifiable->name)
            ->line('Tu producto "'.$this->product->name.'" ha sido rechazado por nuestro equipo de moderación.')
            ->line('**Motivo del rechazo:**')
            ->line($this->product->moderation_note ?? 'No se proporcionó una razón específica.')
            ->line('Puedes editar el producto y volver a enviarlo para revisión.')
            ->action('Ver Producto', url('/app/products/'.$this->product->id.'/edit'))
            ->line('Si tienes alguna pregunta, no dudes en contactarnos.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'moderation_note' => $this->product->moderation_note,
            'rejected_at' => $this->product->rejected_at,
        ];
    }
}
