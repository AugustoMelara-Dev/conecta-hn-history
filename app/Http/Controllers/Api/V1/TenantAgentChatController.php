<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantAgentChatController extends Controller
{
    public function __construct(
        private readonly PrivateAiAccessGate $privateAiAccess,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'tenant_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $tenantId = (string) ($request->header('X-Tenant-ID') ?? $validated['tenant_id'] ?? $user?->tenant_id ?? '');

        if ($tenantId === '' || ! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        $message = mb_strtolower((string) $validated['message']);

        if (str_contains($message, 'vendi') || str_contains($message, 'vendido') || str_contains($message, 'ventas')) {
            $orders = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->whereNotIn('status', [Order::STATUS_CANCELLED]);

            $ordersCount = (clone $orders)->count();
            $grossTotal = (float) (clone $orders)->sum('total');

            return response()->json([
                'answer' => 'Hoy tenes '.$ordersCount.' ordenes registradas por L. '.number_format($grossTotal, 2).'.',
                'source' => 'private_tenant_scope',
                'tenant_id' => $tenantId,
                'data' => [
                    'intent' => 'sales_today',
                    'orders_count' => $ordersCount,
                    'gross_total' => $grossTotal,
                ],
                'meta' => $this->meta($tenantId),
            ]);
        }

        if (str_contains($message, 'reserv')) {
            $appointments = Appointment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('scheduled_at', today())
                ->orderBy('scheduled_at')
                ->get(['id', 'customer_name', 'scheduled_at', 'end_at', 'status']);

            return response()->json([
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
            ]);
        }

        return response()->json([
            'answer' => 'Puedo responder sobre datos privados del negocio cuando la pregunta coincide con capacidades activas y siempre limitado a este tenant.',
            'source' => 'private_tenant_scope',
            'tenant_id' => $tenantId,
            'data' => [
                'intent' => 'unknown',
            ],
            'meta' => $this->meta($tenantId),
        ]);
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
