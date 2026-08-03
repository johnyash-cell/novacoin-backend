<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAdminNotificationsRequest;
use App\Http\Requests\Api\Admin\StoreAdminNotificationRequest;
use App\Http\Resources\AdminNotificationResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Services\Admin\SendsAdminInAppNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(IndexAdminNotificationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : null;
        $endDate = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : null;

        $notifications = AdminNotification::query()
            ->with('admin')
            ->when(
                filled($validated['audience_mode'] ?? null),
                fn ($query) => $query->where('audience_mode', $validated['audience_mode']),
            )
            ->when(
                $startDate !== null && $endDate !== null,
                fn ($query) => $query->whereBetween('sent_at', [$startDate, $endDate]),
            )
            ->orderBy('sent_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Notifications fetched successfully',
            data: AdminNotificationResource::collection($notifications->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
                'filters' => [
                    'audience_mode' => $validated['audience_mode'] ?? null,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ],
            ],
        );
    }

    public function store(
        StoreAdminNotificationRequest $request,
        SendsAdminInAppNotification $sendsAdminInAppNotification,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $adminNotification = $sendsAdminInAppNotification->send(
            admin: $admin,
            validated: $request->validated(),
        );

        return $this->successResponse(
            message: 'Notification sent successfully',
            data: (new AdminNotificationResource($adminNotification))->resolve(),
            statusCode: 201,
        );
    }

    public function filterOptions(): JsonResponse
    {
        $distinctAudienceModes = AdminNotification::query()
            ->whereNotNull('audience_mode')
            ->distinct()
            ->pluck('audience_mode')
            ->all();

        $audienceModeLabels = [
            'all_users' => 'All users',
            'selected_users' => 'Selected users',
        ];

        $audienceModeOptions = [];
        foreach ($distinctAudienceModes as $audienceMode) {
            $audienceModeOptions[] = [
                'value' => $audienceMode,
                'label' => $audienceModeLabels[$audienceMode] ?? ucfirst(str_replace('_', ' ', (string) $audienceMode)),
            ];
        }

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'audience_mode',
                        'label' => 'Audience',
                        'description' => 'Filter by who the notification was sent to',
                        'type' => 'single-select',
                        'options' => $audienceModeOptions,
                    ],
                    [
                        'key' => 'date_range',
                        'label' => 'Date Range',
                        'description' => 'Filter by sent date range (start_date & end_date)',
                        'type' => 'date-range',
                        'options' => null,
                    ],
                ],
                'total_available_filters' => 2,
            ],
        );
    }
}
