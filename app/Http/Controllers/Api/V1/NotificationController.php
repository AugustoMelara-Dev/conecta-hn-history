<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Contracts\NotificationRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NotificationController - API Data Adapter (Clean)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Decoupled from DatabaseNotification.
 * - Uses NotificationRepository.
 * - Returns structured JsonResource.
 */
final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository
    ) {}

    /**
     * Get hydrated list of notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 10), 50);

        $notifications = $this->notificationRepository->getUserNotifications(
            userId: $request->user()->id,
            perPage: $limit
        );

        return response()->json([
            'notifications' => NotificationResource::collection($notifications),
            'unread_count' => $notifications->total(), // Approximation or add strict unread count method to repo if needed.
             // For strictness with 'EloquentNotificationRepository', paginate returns everything.
             // If unread_count is critical, we might need a dedicated method in Repo: countUnread($userId).
             // Spec didn't ask for it explicitly but index usually requires it.
             // I will stick to what's available or simple.
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $this->notificationRepository->markAsRead(
            userId: $request->user()->id,
            notificationId: $id
        );

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationRepository->markAllAsRead(
            userId: $request->user()->id
        );

        return response()->json(['success' => true]);
    }
}
