<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;

/**
 * NotificationController - The Data Hydrator
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE: READ-TIME HYDRATION (JIT)                                  ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║  Role: Middleware between Raw Storage and Frontend Presentation.          ║
 * ║  Function: Intercepts raw file paths and mints fresh signed URLs on read. ║
 * ║  Guarantee: Links delivered by this API are valid for 30 mins from NOW.   ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */
final class NotificationController extends Controller
{
    /**
     * Get hydrated list of notifications.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $limit = min((int) $request->query('limit', 10), 50);

        // 1. Retrieval (Raw Data)
        $notifications = $user->notifications()
            ->orderByRaw('read_at IS NULL DESC') // Unread first
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // 2. Transformation & Hydration (JIT)
        $hydrated = $notifications->map(function (DatabaseNotification $notification) use ($user) {
            $data = $notification->data;
            
            // Virtual Property: action_url
            // Default to whatever was stored (if any), but override for exports
            $actionUrl = $data['action_url'] ?? null;
            
            // DETECTION: Is this an export that needs hydration?
            // checking internal flag 'type' inside data JSON
            if (($data['type'] ?? '') === 'export_ready' && !empty($data['file_path'])) {
                try {
                    // MINTING: Generate Cryptographically Signed URL (TTL 30 min)
                    $actionUrl = URL::temporarySignedRoute(
                        'api.exports.download',
                        now()->addMinutes(30),
                        [
                            'path' => base64_encode($data['file_path']),
                            'tenant' => $user->tenant_id,
                        ]
                    );
                } catch (\Throwable $e) {
                    // Fail safe: deliver without URL if signing fails
                    $actionUrl = null;
                }
            }

            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? 'general',
                'title' => $data['title'] ?? 'Notificación',
                'body' => $data['body'] ?? '',
                'action_url' => $actionUrl, // Hydrated Property
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'notifications' => $hydrated,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a specific notification as read.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}
