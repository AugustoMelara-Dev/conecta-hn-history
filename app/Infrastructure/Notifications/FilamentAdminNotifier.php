<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Contracts\AdminNotifier as OperationsAdminNotifier;
use App\Domain\Sales\Entities\LeadEntity;
use App\Domain\Shared\Contracts\AdminNotifierInterface as LegacyAdminNotifierInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Infrastructure adapter for Filament database notifications.
 */
final class FilamentAdminNotifier implements LegacyAdminNotifierInterface, OperationsAdminNotifier
{
    public function __construct(
        private readonly TenantPermissionGuardInterface $guard,
    ) {}

    public function notifyNewLead(object $lead): void
    {
        $tenant = $this->resolveLeadTenant($lead);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $leadName = $this->resolveLeadName($lead);

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::NOTIFICATIONS_BASIC, [Permission::MARKETING_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Nuevo lead listo para seguimiento')
                ->body("{$leadName} dejo una nueva consulta y conviene responder hoy.")
                ->success()
                ->actions([
                    Action::make('view')
                        ->label('Ver leads')
                        ->url($this->tenantRoute($tenant, 'filament.app.resources.leads.index')),
                ]),
        );
    }

    public function notifyNewReview(object $review): void
    {
        if (! $review instanceof Review) {
            return;
        }

        $tenant = $this->findTenant($review->getAttribute('tenant_id'));

        if (! $tenant instanceof Tenant) {
            return;
        }

        $rating = max(0, (int) ($review->getAttribute('rating') ?? 0));
        $comment = trim((string) ($review->getAttribute('comment') ?? ''));
        $excerpt = $comment !== '' ? mb_strimwidth($comment, 0, 60, '...') : 'Sin comentario adicional.';

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::NOTIFICATIONS_BASIC, [Permission::MARKETING_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Nueva resena recibida')
                ->body("Calificacion: {$rating}/5. {$excerpt}")
                ->success()
                ->actions([
                    Action::make('view')
                        ->label('Ver resenas')
                        ->url($this->tenantRoute($tenant, 'filament.app.resources.reviews.index')),
                ]),
        );
    }

    public function notifyNewOrder(int|string $tenantId, int $orderId, float $total): void
    {
        $tenant = $this->findTenant($tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::CHECKOUT_BASIC, [Permission::ORDERS_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Nueva orden recibida')
                ->body("Orden #{$orderId} por L. ".number_format($total, 2))
                ->success()
                ->actions([
                    Action::make('view')
                        ->label('Ver pedidos')
                        ->url($this->tenantRoute($tenant, 'filament.app.resources.orders.index')),
                ]),
        );
    }

    public function notifyPaymentUpdate(int|string $tenantId, string $status, float $amount): void
    {
        $tenant = $this->findTenant($tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::CATALOG_BASIC, [Permission::BILLING_VIEW, Permission::BILLING_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Actualizacion de pago')
                ->body("Estado: {$status}. Monto: L. ".number_format($amount, 2))
                ->warning()
                ->actions([
                    Action::make('view')
                        ->label('Ir a suscripcion')
                        ->url($this->tenantRoute($tenant, 'filament.app.pages.manage-billing')),
                ]),
        );
    }

    public function notifyAppointmentBooked(object $appointment): void
    {
        if (! $appointment instanceof Appointment) {
            return;
        }

        $tenant = $this->resolveTenantFromModel($appointment);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $customerName = (string) ($appointment->customer_display_name ?? $appointment->customer_name ?? 'Cliente');
        $scheduledAt = $appointment->scheduled_at->format('d/m H:i');

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::APPOINTMENT_BOOKING, [Permission::APPOINTMENTS_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Nueva cita reservada')
                ->body("{$customerName} agenda para {$scheduledAt}. Conviene confirmar detalles hoy.")
                ->success()
                ->actions([
                    Action::make('view')
                        ->label('Ver agenda')
                        ->url($this->tenantRoute($tenant, 'filament.app.resources.appointments.index')),
                ]),
        );
    }

    public function notifyAppointmentReminder(object $appointment): void
    {
        if (! $appointment instanceof Appointment) {
            return;
        }

        $tenant = $this->resolveTenantFromModel($appointment);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $customerName = (string) ($appointment->customer_display_name ?? $appointment->customer_name ?? 'Cliente');
        $scheduledAt = $appointment->scheduled_at->format('d/m H:i');

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::APPOINTMENT_BOOKING, [Permission::APPOINTMENTS_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Recordatorio de cita enviado')
                ->body("Ya se envio el recordatorio para {$customerName} ({$scheduledAt}).")
                ->info()
                ->actions([
                    Action::make('view')
                        ->label('Revisar agenda')
                        ->url($this->tenantRoute($tenant, 'filament.app.resources.appointments.index')),
                ]),
        );
    }

    public function notifyRecoveryOfferReady(object $tenant, ?string $customerName, string $couponCode, float $subtotal): void
    {
        if (! $tenant instanceof Tenant) {
            return;
        }

        $customerLabel = $customerName ?: 'cliente';

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::NOTIFICATIONS_BASIC, [Permission::MARKETING_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Recuperacion de carrito activada')
                ->body("Se genero {$couponCode} para {$customerLabel} por L. ".number_format($subtotal, 2).'.')
                ->warning()
                ->actions([
                    Action::make('view')
                        ->label('Ver marketing')
                        ->url($this->tenantRoute($tenant, 'filament.app.pages.manage-marketing')),
                ]),
        );
    }

    public function notifyRepeatPurchaseOpportunity(object $order): void
    {
        if (! $order instanceof Order) {
            return;
        }

        $tenant = $this->resolveTenantFromModel($order);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $customerName = (string) ($order->customer_name ?? 'Cliente');
        $orderNumber = (string) ($order->order_number ?? '#'.$order->getKey());

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::NOTIFICATIONS_BASIC, [Permission::MARKETING_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Oportunidad de recompra detectada')
                ->body("{$customerName} completo {$orderNumber}. Es buen momento para seguimiento o upsell.")
                ->success()
                ->actions([
                    Action::make('view')
                        ->label('Abrir pedidos')
                        ->url($this->tenantRoute($tenant, 'filament.app.resources.orders.index')),
                ]),
        );
    }

    public function notifyRenewalAttention(object $tenant, int $daysUntilExpiry): void
    {
        if (! $tenant instanceof Tenant) {
            return;
        }

        $planLabel = (string) ($tenant->plan_type_label ?? 'Plan PRO');

        $this->sendToRecipients(
            $this->recipientsForTenant($tenant, Capability::CATALOG_BASIC, [Permission::BILLING_VIEW, Permission::BILLING_MANAGE]),
            fn (): Notification => Notification::make()
                ->title('Renovacion a revisar esta semana')
                ->body("{$tenant->name} vence en {$daysUntilExpiry} dias. {$planLabel} necesita seguimiento de cobro.")
                ->warning()
                ->actions([
                    Action::make('view')
                        ->label('Ir a suscripcion')
                        ->url($this->tenantRoute($tenant, 'filament.app.pages.manage-billing')),
                ]),
        );
    }

    /**
     * @param  array<int, Permission>  $permissions
     * @return Collection<int, User>
     */
    private function recipientsForTenant(Tenant $tenant, Capability $capability, array $permissions): Collection
    {
        $owner = $tenant->user;

        /** @var \Illuminate\Database\Eloquent\Collection<int, TenantMembership> $memberships */
        $memberships = $tenant->memberships()
            ->whereNotNull('accepted_at')
            ->with('user')
            ->get();

        $members = $memberships
            ->map(function (TenantMembership $membership): ?User {
                $user = $membership->user;

                return $user instanceof User ? $user : null;
            })
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->filter(function (User $user) use ($tenant, $capability, $permissions): bool {
                foreach ($permissions as $permission) {
                    if ($this->guard->allows($user, $tenant, $capability, $permission)) {
                        return true;
                    }
                }

                return false;
            });

        return collect([$owner])
            ->filter(fn (?User $user): bool => $user instanceof User && $user->banned_at === null)
            ->merge($members->filter(fn (User $user): bool => $user->banned_at === null))
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  callable(): Notification  $factory
     */
    private function sendToRecipients(Collection $recipients, callable $factory): void
    {
        foreach ($recipients as $recipient) {
            $factory()->sendToDatabase($recipient);
        }
    }

    private function findTenant(int|string|null $tenantId): ?Tenant
    {
        if ($tenantId === null || $tenantId === '') {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function resolveLeadTenant(mixed $lead): ?Tenant
    {
        if ($lead instanceof LeadEntity) {
            return $this->findTenant($lead->tenantId);
        }

        if (is_object($lead) && method_exists($lead, 'getAttribute')) {
            /** @var mixed $tenantId */
            $tenantId = $lead->getAttribute('tenant_id');

            return $this->findTenant($tenantId);
        }

        return null;
    }

    private function resolveLeadName(mixed $lead): string
    {
        if ($lead instanceof LeadEntity) {
            return $lead->contactData['name'];
        }

        if (is_object($lead) && method_exists($lead, 'getAttribute')) {
            return (string) ($lead->getAttribute('customer_name')
                ?? $lead->getAttribute('name')
                ?? 'Nuevo lead');
        }

        return 'Nuevo lead';
    }

    private function resolveTenantFromModel(object $model): ?Tenant
    {
        if (method_exists($model, 'tenant') && $model->relationLoaded('tenant')) {
            $tenant = $model->getRelation('tenant');

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        /** @var mixed $tenantId */
        $tenantId = $model->getAttribute('tenant_id');

        return $this->findTenant($tenantId);
    }

    private function tenantRoute(Tenant $tenant, string $routeName, array $parameters = []): string
    {
        return route($routeName, array_merge([
            'tenant' => (string) ($tenant->slug ?? ''),
        ], $parameters), false);
    }
}
