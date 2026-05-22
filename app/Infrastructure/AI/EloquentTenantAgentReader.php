<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Models\Appointment;
use App\Models\Order;

final class EloquentTenantAgentReader
{
    /**
     * @return array<string, mixed>
     */
    public function answer(string $tenantId, string $message): array
    {
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'vendi') || str_contains($normalized, 'vendido') || str_contains($normalized, 'ventas')) {
            // INFRA_TENANT_FILTER: private AI reader always applies the requested tenant id before reading orders.
            $orders = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->whereNotIn('status', [Order::STATUS_CANCELLED]);

            $ordersCount = (clone $orders)->count();
            $grossTotal = (float) (clone $orders)->sum('total');

            return [
                'answer' => 'Hoy tenes '.$ordersCount.' ordenes registradas por L. '.number_format($grossTotal, 2).'.',
                'source' => 'private_tenant_scope',
                'tenant_id' => $tenantId,
                'data' => [
                    'intent' => 'sales_today',
                    'orders_count' => $ordersCount,
                    'gross_total' => $grossTotal,
                ],
                'meta' => $this->meta($tenantId),
            ];
        }

        if (str_contains($normalized, 'reserv')) {
            // INFRA_TENANT_FILTER: private AI reader always applies the requested tenant id before reading appointments.
            $appointments = Appointment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('scheduled_at', today())
                ->orderBy('scheduled_at')
                ->get(['id', 'customer_name', 'scheduled_at', 'end_at', 'status']);

            return [
                'answer' => $appointments->isEmpty()
                    ? 'Hoy no hay reservas registradas para este negocio.'
                    : 'Estas son las reservas de hoy para este negocio.',
                'source' => 'private_tenant_scope',
                'tenant_id' => $tenantId,
                'data' => [
                    'intent' => 'appointments',
                    'appointments' => $appointments->map(fn (Appointment $appointment): array => [
                        'id' => $appointment->getKey(),
                        'customer_name' => $appointment->customer_name,
                        'scheduled_at' => optional($appointment->scheduled_at)?->toIso8601String(),
                        'end_at' => optional($appointment->end_at)?->toIso8601String(),
                        'status' => $appointment->status,
                    ])->values()->all(),
                ],
                'meta' => $this->meta($tenantId),
            ];
        }

        return [
            'answer' => 'Puedo responder sobre datos privados del negocio cuando la pregunta coincide con capacidades activas y siempre limitado a este tenant.',
            'source' => 'private_tenant_scope',
            'tenant_id' => $tenantId,
            'data' => ['intent' => 'unknown'],
            'meta' => $this->meta($tenantId),
        ];
    }

    /**
     * @return array{mode:string,llm_used:false,privacy_scope:string,tenant_id:string}
     */
    private function meta(string $tenantId): array
    {
        return [
            'mode' => 'deterministic_tenant_private_agent',
            'llm_used' => false,
            'privacy_scope' => 'tenant_private_only',
            'tenant_id' => $tenantId,
        ];
    }
}
