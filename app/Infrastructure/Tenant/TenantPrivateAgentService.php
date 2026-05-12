<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TenantPrivateAgentService
{
    /**
     * @return array<string, mixed>
     */
    public function answer(Tenant $tenant, string $message): array
    {
        $intent = $this->intentFor($message);

        return match ($intent) {
            'sales_today' => $this->salesToday($tenant),
            'appointments' => $this->appointments($tenant),
            'catalog' => $this->catalog($tenant),
            default => $this->fallback($tenant),
        };
    }

    private function intentFor(string $message): string
    {
        $normalized = Str::of($message)->lower()->ascii()->toString();

        if (Str::contains($normalized, ['venta', 'vendido', 'vendi', 'ingreso', 'pedido', 'orden'])) {
            return 'sales_today';
        }

        if (Str::contains($normalized, ['reserva', 'reservado', 'cita', 'agenda', 'hora'])) {
            return 'appointments';
        }

        if (Str::contains($normalized, ['producto', 'servicio', 'catalogo', 'inventario', 'publicado'])) {
            return 'catalog';
        }

        return 'fallback';
    }

    /**
     * @return array<string, mixed>
     */
    private function salesToday(Tenant $tenant): array
    {
        $query = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereDate('created_at', today());

        if (Schema::hasColumn('orders', 'status')) {
            $query->where('status', '!=', Order::STATUS_CANCELLED);
        }

        $count = (int) (clone $query)->count();
        $total = Schema::hasColumn('orders', 'total')
            ? (float) (clone $query)->sum('total')
            : 0.0;

        return $this->payload(
            "Hoy tienes {$count} pedidos no cancelados por un total aproximado de ".$this->money($total).'.',
            [
                'intent' => 'sales_today',
                'orders_count' => $count,
                'gross_total' => $total,
            ],
            $tenant
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function appointments(Tenant $tenant): array
    {
        if (! Schema::hasTable('appointments')) {
            return $this->payload(
                'Todavia no hay tabla de reservas disponible en esta instalacion.',
                ['intent' => 'appointments', 'appointments' => []],
                $tenant
            );
        }

        $query = Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereDate('scheduled_at', today());

        if (Schema::hasColumn('appointments', 'status')) {
            $query->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED]);
        }

        $appointments = $query
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'scheduled_at', 'customer_name', 'status'])
            ->map(static fn (Appointment $appointment): array => [
                'id' => $appointment->getKey(),
                'time' => $appointment->scheduled_at?->format('H:i'),
                'customer_name' => $appointment->customer_name,
                'status' => $appointment->status,
            ])
            ->values()
            ->all();

        $count = count($appointments);
        $answer = $count > 0
            ? "Hoy tienes {$count} reservas proximas. Te muestro hasta cinco para mantener la respuesta breve."
            : 'Hoy no encuentro reservas pendientes o confirmadas para este negocio.';

        return $this->payload($answer, [
            'intent' => 'appointments',
            'appointments' => $appointments,
        ], $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(Tenant $tenant): array
    {
        $query = Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey());

        if (Schema::hasColumn('products', 'is_visible')) {
            $query->where('is_visible', true);
        }

        $visibleCount = (int) (clone $query)->count();
        $lowStockCount = 0;

        if (
            Schema::hasColumn('products', 'manage_stock')
            && Schema::hasColumn('products', 'stock_quantity')
            && Schema::hasColumn('products', 'low_stock_threshold')
        ) {
            $lowStockCount = (int) (clone $query)
                ->where('manage_stock', true)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->count();
        }

        return $this->payload(
            "Tienes {$visibleCount} productos visibles. Detecte {$lowStockCount} productos con stock bajo.",
            [
                'intent' => 'catalog',
                'visible_products_count' => $visibleCount,
                'low_stock_count' => $lowStockCount,
            ],
            $tenant
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(Tenant $tenant): array
    {
        return $this->payload(
            'Puedo ayudarte con ventas de hoy, reservas de hoy y estado basico del catalogo. No voy a inventar datos fuera de este negocio.',
            ['intent' => 'fallback'],
            $tenant
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(string $answer, array $data, Tenant $tenant): array
    {
        return [
            'answer' => $answer,
            'data' => $data,
            'meta' => [
                'mode' => 'deterministic_tenant_private_agent',
                'llm_used' => false,
                'llm_enabled' => (bool) config('conecta.features.tenant_private_rag_llm_enabled', false),
                'privacy_scope' => 'tenant_private_only',
                'tenant_id' => (string) $tenant->getKey(),
            ],
        ];
    }

    private function money(float $value): string
    {
        return 'L '.number_format($value, 2, '.', ',');
    }
}
