<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\User;

final class BuyerOnboardingService
{
    /**
     * @return array{
     *   progress:int,
     *   milestones:list<array{key:string,title:string,copy:string,completed:bool}>,
     *   nextAction:array{key:string,title:string,copy:string,href:string}
     * }
     */
    public function snapshot(User $user): array
    {
        $hasVerifiedEmail = $user->hasVerifiedEmail();
        $hasFavorites = $user->favorites()->exists();
        $hasFollowedTenants = $user->following()->exists();
        $hasOrders = Order::query()->withoutGlobalScopes()->where('user_id', $user->getKey())->exists();
        $hasAppointments = Appointment::query()->withoutGlobalScopes()->where('user_id', $user->getKey())->exists();

        $milestones = [
            $this->milestone('account_verified', 'Cuenta verificada', 'Confirma tu correo para proteger tu cuenta y recibir alertas.', $hasVerifiedEmail),
            $this->milestone('favorite_saved', 'Primer favorito', 'Guarda productos para encontrarlos mas rapido.', $hasFavorites),
            $this->milestone('tenant_followed', 'Primer negocio seguido', 'Sigue negocios para enterarte de novedades y promociones.', $hasFollowedTenants),
            $this->milestone('first_order', 'Primera orden o reserva', 'Completa una interaccion real para personalizar tu experiencia.', $hasOrders || $hasAppointments),
        ];

        $completed = collect($milestones)->where('completed', true)->count();
        $progress = (int) round(($completed / max(1, count($milestones))) * 100);
        $nextPending = collect($milestones)->firstWhere('completed', false);

        $nextAction = match ((string) ($nextPending['key'] ?? 'done')) {
            'account_verified' => [
                'key' => 'account_verified',
                'title' => 'Verificar cuenta',
                'copy' => 'Confirma tu email para activar alertas.',
                'href' => route('verification.notice'),
            ],
            'favorite_saved' => [
                'key' => 'favorite_saved',
                'title' => 'Explorar directorio',
                'copy' => 'Encuentra negocios y guarda favoritos.',
                'href' => route('directory.index'),
            ],
            'tenant_followed' => [
                'key' => 'tenant_followed',
                'title' => 'Seguir negocios',
                'copy' => 'Empieza siguiendo tu negocio preferido.',
                'href' => route('public.explore'),
            ],
            'first_order' => [
                'key' => 'first_order',
                'title' => 'Completar primera accion',
                'copy' => 'Haz una reserva o pedido para desbloquear recordatorios.',
                'href' => route('public.explore'),
            ],
            default => [
                'key' => 'done',
                'title' => 'Checklist completo',
                'copy' => 'Tu cuenta esta lista para una experiencia completa.',
                'href' => route('public.explore'),
            ],
        };

        return [
            'progress' => $progress,
            'milestones' => $milestones,
            'nextAction' => $nextAction,
        ];
    }

    /**
     * @return array{key:string,title:string,copy:string,completed:bool}
     */
    private function milestone(string $key, string $title, string $copy, bool $completed): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'copy' => $copy,
            'completed' => $completed,
        ];
    }
}
