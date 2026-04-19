<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessClaimDispute;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BusinessClaimDisputeController extends Controller
{
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:120'],
            'details' => ['nullable', 'string', 'max:2000'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $request->user();

        $dispute = BusinessClaimDispute::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'reporter_user_id' => $user?->getAuthIdentifier(),
            'reporter_email' => $validated['reporter_email'] ?? $user?->email,
            'status' => 'open',
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
        ]);

        $tenant->forceFill([
            'publication_paused_at' => now(),
            'publication_pause_reason' => 'Disputa de titularidad abierta: '.$validated['reason'],
        ])->save();

        return response()->json([
            'message' => 'Disputa registrada. El negocio queda pausado mientras revisamos la titularidad.',
            'data' => [
                'dispute_id' => $dispute->id,
                'status' => $dispute->status,
            ],
        ], 202);
    }
}
