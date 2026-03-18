<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Contracts\AdminNotifier;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Infrastructure adapter for Filament database notifications.
 */
final class FilamentAdminNotifier implements AdminNotifier
{
    public function notifyNewLead(object $lead): void
    {
        if (! $lead instanceof Lead) {
            return;
        }

        $tenantOwner = $this->getTenantOwner((string) $lead->getAttribute('tenant_id'));

        if (! $tenantOwner instanceof User) {
            return;
        }

        $leadName = (string) ($lead->getAttribute('customer_name')
            ?? $lead->getAttribute('name')
            ?? 'Nuevo contacto');

        Notification::make()
            ->title('Nuevo cliente potencial')
            ->body("{$leadName} ha enviado una nueva consulta.")
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Ver lead')
                    ->url("/admin/leads/{$lead->getKey()}"),
            ])
            ->sendToDatabase($tenantOwner);
    }

    public function notifyNewReview(object $review): void
    {
        if (! $review instanceof Review) {
            return;
        }

        $tenantOwner = $this->getTenantOwner((string) $review->getAttribute('tenant_id'));

        if (! $tenantOwner instanceof User) {
            return;
        }

        $rating = (int) ($review->getAttribute('rating') ?? 0);
        $comment = (string) ($review->getAttribute('comment') ?? '');
        $excerpt = mb_strimwidth($comment, 0, 50, '...');

        Notification::make()
            ->title("Nueva reseña {$this->stars($rating)}")
            ->body("Calificación: {$rating}/5\n\"{$excerpt}\"")
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Ver reseña')
                    ->url("/admin/reviews/{$review->getKey()}"),
            ])
            ->sendToDatabase($tenantOwner);
    }

    public function notifyNewOrder(int $tenantId, int $orderId, float $total): void
    {
        $tenantOwner = $this->getTenantOwner((string) $tenantId);

        if (! $tenantOwner instanceof User) {
            return;
        }

        Notification::make()
            ->title('Nueva orden recibida')
            ->body("Orden #{$orderId} por L. ".number_format($total, 2))
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Ver orden')
                    ->url("/admin/orders/{$orderId}"),
            ])
            ->sendToDatabase($tenantOwner);
    }

    public function notifyPaymentUpdate(int $tenantId, string $status, float $amount): void
    {
        $tenantOwner = $this->getTenantOwner((string) $tenantId);

        if (! $tenantOwner instanceof User) {
            return;
        }

        Notification::make()
            ->title('Actualización de pago')
            ->body("Estado: {$status}. Monto: L. ".number_format($amount, 2))
            ->warning()
            ->sendToDatabase($tenantOwner);
    }

    private function getTenantOwner(string $tenantId): ?User
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return null;
        }

        /** @var User|null $owner */
        $owner = $tenant->user()->first();

        return $owner;
    }

    private function stars(int $rating): string
    {
        if ($rating <= 0) {
            return '';
        }

        return str_repeat('*', $rating);
    }
}
