<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\TenantGovernance\Repositories\NotificationRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NotificationController extends Controller
{
    public function __construct(
        private NotificationRepository $notificationRepository
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $notifications = $this->notificationRepository->getUnreadPaginated(10);

        return \Illuminate\Http\Resources\Json\JsonResource::collection($notifications);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $this->notificationRepository->markAsRead($id);

        return response()->json(['status' => 'success']);
    }
}
