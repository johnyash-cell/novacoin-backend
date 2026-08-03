<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexUserNotificationsRequest;
use App\Http\Resources\UserInAppNotificationResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\AdminNotification;
use App\Models\AdminNotificationRecipient;
use App\Models\User;
use App\Services\Activity\MarksUserInAppNotificationAsRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(IndexUserNotificationsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 20;
        $unreadOnly = (bool) ($validated['unread_only'] ?? false);

        $notifications = AdminNotificationRecipient::query()
            ->with('adminNotification')
            ->where('user_id', $user->id)
            ->when(
                $unreadOnly,
                fn ($query) => $query->whereNull('read_at'),
            )
            ->orderBy(
                AdminNotification::query()
                    ->select('sent_at')
                    ->whereColumn(
                        'admin_notifications.id',
                        'admin_notification_recipients.admin_notification_id',
                    )
                    ->limit(1),
                $sortBy === 'newest' ? 'desc' : 'asc',
            )
            ->paginate($perPage);

        $unreadCount = AdminNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->successResponse(
            message: 'Notifications fetched successfully',
            data: UserInAppNotificationResource::collection($notifications->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
                'unread_count' => $unreadCount,
                'filters' => [
                    'unread_only' => $unreadOnly,
                ],
            ],
        );
    }

    public function show(AdminNotificationRecipient $notification): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($notification->user_id === $user->id, 404);

        $notification->loadMissing('adminNotification');

        return $this->successResponse(
            message: 'Notification fetched successfully',
            data: (new UserInAppNotificationResource($notification))->resolve(),
        );
    }

    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $unreadCount = AdminNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->successResponse(
            message: 'Unread notification count fetched successfully',
            data: [
                'unread_count' => $unreadCount,
            ],
        );
    }

    public function markAsRead(
        AdminNotificationRecipient $notification,
        MarksUserInAppNotificationAsRead $marksUserInAppNotificationAsRead,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $updatedRecipient = $marksUserInAppNotificationAsRead->markOne($user, $notification);

        return $this->successResponse(
            message: 'Notification marked as read',
            data: (new UserInAppNotificationResource($updatedRecipient))->resolve(),
        );
    }

    public function markAllAsRead(
        MarksUserInAppNotificationAsRead $marksUserInAppNotificationAsRead,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $markedCount = $marksUserInAppNotificationAsRead->markAll($user);

        return $this->successResponse(
            message: 'All notifications marked as read',
            data: [
                'marked_count' => $markedCount,
                'unread_count' => 0,
            ],
        );
    }

    public function filterOptions(): JsonResponse
    {
        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'unread_only',
                        'label' => 'Unread only',
                        'description' => 'Show only unread notifications',
                        'type' => 'single-select',
                        'options' => [
                            ['value' => '1', 'label' => 'Unread only'],
                            ['value' => '0', 'label' => 'All notifications'],
                        ],
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }
}
