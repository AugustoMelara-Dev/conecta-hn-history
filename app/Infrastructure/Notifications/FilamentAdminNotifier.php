<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Contracts\AdminNotifier;
use App\Models\Lead;
use App\Models\Review;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

/**
 * FilamentAdminNotifier - Infrastructure Adapter for Notifications
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE LAYER                                                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This class implements the Domain contract using the Filament library.   ║
 * ║  It effectively isolates the Domain from the Filament dependency.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class FilamentAdminNotifier implements AdminNotifier
{
    /**
     * Notify tenant owner about a new lead.
     */
    public function notifyNewLead(object $lead): void
    {
        /** @var \App\Models\Lead $lead */
        // ... (assuming logic can handle object or uses duck typing)
        // If necessary, add instanceof check:
        // if (!$lead instanceof Lead) return;
        $tenantOwner = $this->getTenantOwner($lead->tenant_id);

        if (! $tenantOwner) {
            return;
        }

        Notification::make()
            ->title('¡Nuevo Cliente Potencial! 🎉')
            ->body("Un cliente ha contactado contigo vía {$lead->source}.")
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Ver Lead')
                    ->url("/admin/leads/{$lead->id}")
                    ->markAsRead(),
            ])
            ->sendToDatabase($tenantOwner);
    }

    /**
     * Notify tenant owner about a new review.
     */
    public function notifyNewReview(object $review): void
    {
        /** @var \App\Models\Review $review */
        $tenantOwner = $this->getTenantOwner($review->tenant_id);

        if (! $tenantOwner) {
            return;
        }

        $stars = str_repeat('⭐', $review->rating);
        $excerpt = substr($review->comment ?? '', 0, 50).(strlen($review->comment ?? '') > 50 ? '...' : '');

        Notification::make()
            ->title("Nueva Reseña {$stars}")
            ->body("Calificación: {$review->rating}/5\n\"{$excerpt}\"")
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Ver Reseña')
                    ->url("/admin/reviews/{$review->id}")
                    ->markAsRead(),
            ])
            ->sendToDatabase($tenantOwner);
    }

    /**
     * Notify tenant owner about a new order.
     */
    public function notifyNewOrder(int $tenantId, int $orderId, float $total): void
    {
        $tenantOwner = $this->getTenantOwner($tenantId);

        if (! $tenantOwner) {
            return;
        }

        Notification::make()
            ->title('¡Nueva Orden Recibida! 🛍️')
            ->body("Orden #{$orderId} por L. ".number_format($total, 2))
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Ver Orden')
                    ->url("/admin/orders/{$orderId}")
                    ->markAsRead(),
            ])
            ->sendToDatabase($tenantOwner);
    }

    /**
     * Notify tenant owner about payment status update.
     */
    public function notifyPaymentUpdate(int $tenantId, string $status, float $amount): void
    {
        // Implementation stub or actual logic
    }

    /**
     * Helper to find tenant owner.
     */
    private function getTenantOwner(int $tenantId): ?User
    {
        // Assuming Tenant model has user_id or relationship.
        // Or specific logic to find owner.
        // For strictness, checking App\Domain\TenantGovernance\Models\Tenant
        $tenant = \App\Domain\TenantGovernance\Models\Tenant::find($tenantId);

        return $tenant?->user;
    }
}
