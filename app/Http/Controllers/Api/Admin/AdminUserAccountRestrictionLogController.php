<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserAccountRestrictionLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexUserAccountRestrictionLogsRequest;
use App\Http\Resources\AdminUserAccountRestrictionLogResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserAccountRestrictionLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AdminUserAccountRestrictionLogController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(User $user): JsonResponse
    {
        $distinctActions = UserAccountRestrictionLog::query()
            ->forUser($user->id)
            ->whereNotNull('action')
            ->distinct()
            ->pluck('action')
            ->all();

        $actionOptions = [];
        foreach ($distinctActions as $actionValue) {
            $action = UserAccountRestrictionLogAction::tryFrom((string) $actionValue);

            $actionOptions[] = [
                'value' => (string) $actionValue,
                'label' => $action?->label()
                    ?? ucfirst(str_replace('_', ' ', (string) $actionValue)),
            ];
        }

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'action',
                        'label' => 'Action',
                        'description' => 'Filter by restriction action type',
                        'type' => 'single-select',
                        'options' => $actionOptions,
                    ],
                    [
                        'key' => 'date_range',
                        'label' => 'Date Range',
                        'description' => 'Filter by log date (start_date & end_date)',
                        'type' => 'date-range',
                        'options' => null,
                    ],
                ],
                'total_available_filters' => 2,
            ],
        );
    }

    public function indexForUser(IndexUserAccountRestrictionLogsRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $logs = UserAccountRestrictionLog::query()
            ->forUser($user->id)
            ->when(
                filled($validated['action'] ?? null),
                fn ($query) => $query->where('action', $validated['action']),
            )
            ->when(
                filled($validated['start_date'] ?? null) && filled($validated['end_date'] ?? null),
                function ($query) use ($validated) {
                    $start = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
                    $end = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();

                    $query->whereBetween('created_at', [$start, $end]);
                },
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $adminIds = collect($logs->items())
            ->pluck('performed_by_admin_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $adminsById = $adminIds === []
            ? collect()
            : Admin::query()->whereIn('id', $adminIds)->get()->keyBy('id');

        foreach ($logs->items() as $log) {
            $log->setAttribute(
                'resolved_performed_by_admin',
                $adminsById->get($log->performed_by_admin_id),
            );
        }

        return $this->successResponse(
            message: 'User account restriction logs fetched successfully',
            data: AdminUserAccountRestrictionLogResource::collection($logs->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
                'filters' => [
                    'action' => $validated['action'] ?? null,
                    'sort_by' => $sortBy,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ],
                'user_id' => $user->id,
            ],
        );
    }
}
