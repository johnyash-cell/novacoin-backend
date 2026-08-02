<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexUserPageVisitLogsRequest;
use App\Http\Resources\AdminAggregatedPageVisitResource;
use App\Http\Resources\UserPageVisitLogResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Models\UserPageVisitLog;
use App\Services\Admin\AggregatesAdminPageVisitMetrics;
use App\Services\Admin\PaginatesAggregatedAdminPageVisitListing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserPageVisitLogController extends Controller
{
    use RespondsWithApiEnvelope;

    public function overview(AggregatesAdminPageVisitMetrics $aggregatesAdminPageVisitMetrics): JsonResponse
    {
        return $this->successResponse(
            message: 'Page visit summary metrics fetched successfully',
            data: $aggregatesAdminPageVisitMetrics->aggregate(),
        );
    }

    public function index(
        IndexUserPageVisitLogsRequest $request,
        PaginatesAggregatedAdminPageVisitListing $paginatesAggregatedAdminPageVisitListing,
        AggregatesAdminPageVisitMetrics $aggregatesAdminPageVisitMetrics,
    ): JsonResponse {
        $validated = $request->validated();

        $aggregatedPageVisits = $paginatesAggregatedAdminPageVisitListing->paginate($validated);

        return $this->aggregatedPageVisitIndexResponse(
            aggregatedPageVisits: $aggregatedPageVisits,
            validated: $validated,
            summaryMetrics: $aggregatesAdminPageVisitMetrics->aggregate(),
        );
    }

    public function indexForUser(IndexUserPageVisitLogsRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $pageVisitLogs = $this->buildRawPageVisitLogQuery($validated)
            ->where('user_id', $user->id)
            ->paginate($validated['per_page'] ?? 10);

        return $this->rawPageVisitLogIndexResponse(
            pageVisitLogs: $pageVisitLogs,
            validated: $validated,
            scopedUser: $user,
        );
    }

    public function filterOptions(): JsonResponse
    {
        $distinctPagePaths = UserPageVisitLog::query()
            ->whereNotNull('page_path')
            ->distinct()
            ->orderBy('page_path')
            ->pluck('page_path')
            ->all();

        $pagePathOptions = [];
        foreach ($distinctPagePaths as $pagePath) {
            $pagePathOptions[] = [
                'value' => $pagePath,
                'label' => $pagePath,
            ];
        }

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'page_path',
                        'label' => 'Page',
                        'description' => 'Filter by the visited page path',
                        'type' => 'single-select',
                        'options' => $pagePathOptions,
                    ],
                    [
                        'key' => 'date_range',
                        'label' => 'Date range',
                        'description' => 'Filter by visit date (start_date and end_date)',
                        'type' => 'date-range',
                        'options' => null,
                    ],
                ],
                'total_available_filters' => 2,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildRawPageVisitLogQuery(array $validated): Builder
    {
        $sortBy = $validated['sort_by'] ?? 'newest';

        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : null;
        $endDate = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : null;

        return UserPageVisitLog::query()
            ->when(
                filled($validated['page_path'] ?? null),
                fn (Builder $query) => $query->where('page_path', $validated['page_path']),
            )
            ->when(
                filled($validated['search'] ?? null),
                fn (Builder $query) => $query->where(function (Builder $searchQuery) use ($validated): void {
                    $searchTerm = '%'.$validated['search'].'%';

                    $searchQuery
                        ->where('page_path', 'like', $searchTerm)
                        ->orWhere('page_title', 'like', $searchTerm);
                }),
            )
            ->when(
                $startDate !== null && $endDate !== null,
                fn (Builder $query) => $query->whereBetween('visited_at', [$startDate, $endDate]),
            )
            ->orderBy('visited_at', $sortBy === 'newest' ? 'desc' : 'asc');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{
     *     total_visits: int,
     *     unique_visitors: int,
     *     today_visits: int,
     *     this_week_visits: int,
     * }  $summaryMetrics
     */
    private function aggregatedPageVisitIndexResponse(
        LengthAwarePaginator $aggregatedPageVisits,
        array $validated,
        array $summaryMetrics,
    ): JsonResponse {
        return $this->successResponse(
            message: 'Page visits fetched successfully',
            data: AdminAggregatedPageVisitResource::collection($aggregatedPageVisits->items())->resolve(),
            meta: [
                'summary' => $summaryMetrics,
                'pagination' => [
                    'current_page' => $aggregatedPageVisits->currentPage(),
                    'per_page' => $aggregatedPageVisits->perPage(),
                    'total' => $aggregatedPageVisits->total(),
                    'last_page' => $aggregatedPageVisits->lastPage(),
                ],
                'filters' => [
                    'search' => $validated['search'] ?? null,
                    'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                    'page_path' => $validated['page_path'] ?? null,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function rawPageVisitLogIndexResponse(
        LengthAwarePaginator $pageVisitLogs,
        array $validated,
        ?User $scopedUser = null,
    ): JsonResponse {
        return $this->successResponse(
            message: 'User page visit logs fetched successfully',
            data: UserPageVisitLogResource::collection($pageVisitLogs->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $pageVisitLogs->currentPage(),
                    'per_page' => $pageVisitLogs->perPage(),
                    'total' => $pageVisitLogs->total(),
                    'last_page' => $pageVisitLogs->lastPage(),
                ],
                'filters' => [
                    'user_id' => $scopedUser?->id ?? (isset($validated['user_id']) ? (int) $validated['user_id'] : null),
                    'search' => $validated['search'] ?? null,
                    'page_path' => $validated['page_path'] ?? null,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ],
            ],
        );
    }
}
