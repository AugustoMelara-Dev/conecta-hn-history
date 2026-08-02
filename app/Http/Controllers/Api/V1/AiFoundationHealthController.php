<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Observability\AiFoundationHealth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AiFoundationHealthController extends Controller
{
    public function __invoke(Request $request, AiFoundationHealth $health): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        $snapshot = $health->snapshot();
        $degradedComponents = [];

        if ($snapshot->registryStatus !== 'ready') {
            $degradedComponents[] = 'registry';
        }

        if ($snapshot->accountingPending > 0) {
            $degradedComponents[] = 'accounting_pending';
        }

        if ($snapshot->retentionBacklog > 0) {
            $degradedComponents[] = 'retention_backlog';
        }

        return response()->json([
            'status' => $snapshot->ready ? 'ready' : 'degraded',
            'registry' => $snapshot->registryStatus,
            'eligible_routes' => $snapshot->eligibleRouteCount,
            'accounting_pending' => $snapshot->accountingPending,
            'retention_backlog' => $snapshot->retentionBacklog,
            'last_successful_evaluation_at' => $snapshot->lastSuccessfulEvaluationAt,
            'degraded_components' => $degradedComponents,
        ]);
    }
}
