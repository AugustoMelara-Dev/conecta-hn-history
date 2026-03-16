<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles browser push subscription registration and removal.
 */
final class PushSubscriptionController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:1000',
            'keys.auth' => 'required|string|max:255',
            'keys.p256dh' => 'required|string|max:255',
            'contentEncoding' => 'nullable|string|max:50',
        ]);

        $request->user()->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('contentEncoding')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Web Push subscription updated.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:1000',
        ]);

        $request->user()->deletePushSubscription($request->input('endpoint'));

        return response()->json([
            'status' => 'success',
            'message' => 'Web Push subscription deleted.',
        ]);
    }
}
