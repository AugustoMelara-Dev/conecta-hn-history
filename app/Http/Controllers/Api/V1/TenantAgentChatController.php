<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Http\Controllers\Controller;
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
        $tenantId = (string) ($validated['tenant_id'] ?? $user?->tenant_id ?? '');

        if ($tenantId === '' || ! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        $message = mb_strtolower((string) $validated['message']);

        if (str_contains($message, 'vendi') || str_contains($message, 'vendido') || str_contains($message, 'ventas')) {
            $orders = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', today());

            return response()->json([
                'answer' => 'Hoy tenes '.$orders->count().' ordenes registradas por L. '.number_format((float) $orders->sum('total'), 2).'.',
                'source' => 'private_tenant_scope',
                'tenant_id' => $tenantId,
            ]);
        }

        if (str_contains($message, 'reserv')) {
            return response()->json([
                'answer' => 'La IA privada esta habilitada para este tenant, pero las reservas se responden desde el modulo operativo de Filament cuando el tenant tiene esa capacidad activa.',
                'source' => 'private_tenant_scope',
                'tenant_id' => $tenantId,
            ]);
        }

        return response()->json([
            'answer' => 'Puedo responder sobre datos privados del negocio cuando la pregunta coincide con capacidades activas y siempre limitado a este tenant.',
            'source' => 'private_tenant_scope',
            'tenant_id' => $tenantId,
        ]);
    }
}
