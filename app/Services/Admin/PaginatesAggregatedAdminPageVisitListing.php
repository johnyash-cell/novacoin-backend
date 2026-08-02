<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\UserPageVisitLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaginatesAggregatedAdminPageVisitListing
{
    /**
     * @param  array<string, mixed>  $validated
     * @return LengthAwarePaginator<int, object>
     */
    public function paginate(array $validated): LengthAwarePaginator
    {
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = (int) ($validated['per_page'] ?? 10);
        $sortDirection = $sortBy === 'newest' ? 'desc' : 'asc';

        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : null;
        $endDate = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : null;

        $searchTerm = filled($validated['search'] ?? null)
            ? '%'.$validated['search'].'%'
            : null;

        $groupedQuery = UserPageVisitLog::query()
            ->select([
                'page_path',
                'user_id',
                DB::raw('COUNT(*) as visit_count'),
                DB::raw('MAX(visited_at) as last_seen_at'),
            ])
            ->when(
                filled($validated['user_id'] ?? null),
                fn ($query) => $query->where('user_id', $validated['user_id']),
            )
            ->when(
                filled($validated['page_path'] ?? null),
                fn ($query) => $query->where('page_path', $validated['page_path']),
            )
            ->when(
                $searchTerm !== null,
                fn ($query) => $query->where(function ($searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->where('page_path', 'like', $searchTerm)
                        ->orWhere('page_title', 'like', $searchTerm);
                }),
            )
            ->when(
                $startDate !== null && $endDate !== null,
                fn ($query) => $query->whereBetween('visited_at', [$startDate, $endDate]),
            )
            ->groupBy('page_path', 'user_id');

        $paginatedGroups = DB::query()
            ->fromSub($groupedQuery->toBase(), 'grouped_page_visits')
            ->orderBy('last_seen_at', $sortDirection)
            ->paginate($perPage);

        /** @var Collection<int, object> $groupItems */
        $groupItems = collect($paginatedGroups->items());

        if ($groupItems->isEmpty()) {
            return $paginatedGroups;
        }

        $latestVisitRows = $this->loadLatestVisitRowsForGroups($groupItems);
        $usersById = $this->loadUsersForGroups($groupItems);

        $enrichedItems = $groupItems->map(function (object $group) use ($latestVisitRows, $usersById): object {
            $visitorKey = $this->buildVisitorKey($group->page_path, $group->user_id);
            $latestVisit = $latestVisitRows->get($visitorKey);
            $user = $group->user_id !== null ? $usersById->get((int) $group->user_id) : null;

            $group->page_title = $latestVisit?->page_title;
            $group->device_type = $latestVisit?->device_type;
            $group->traffic_source = $latestVisit?->traffic_source;
            $group->visitor_first_name = $user?->first_name;
            $group->visitor_last_name = $user?->last_name;
            $group->visitor_email = $user?->email;

            return $group;
        });

        return new Paginator(
            $enrichedItems,
            $paginatedGroups->total(),
            $paginatedGroups->perPage(),
            $paginatedGroups->currentPage(),
            [
                'path' => $paginatedGroups->path(),
                'pageName' => $paginatedGroups->getPageName(),
            ],
        );
    }

    /**
     * @param  Collection<int, object>  $groupItems
     * @return Collection<string, UserPageVisitLog>
     */
    private function loadLatestVisitRowsForGroups(Collection $groupItems): Collection
    {
        $latestVisitQuery = UserPageVisitLog::query();

        $latestVisitQuery->where(function (EloquentBuilder $outerQuery) use ($groupItems): void {
            foreach ($groupItems as $group) {
                $outerQuery->orWhere(function (EloquentBuilder $groupQuery) use ($group): void {
                    $groupQuery->where('page_path', $group->page_path)
                        ->where('visited_at', $group->last_seen_at);

                    if ($group->user_id === null) {
                        $groupQuery->whereNull('user_id');
                    } else {
                        $groupQuery->where('user_id', $group->user_id);
                    }
                });
            }
        });

        return $latestVisitQuery
            ->get()
            ->keyBy(fn (UserPageVisitLog $visitLog): string => $this->buildVisitorKey(
                $visitLog->page_path,
                $visitLog->user_id,
            ));
    }

    /**
     * @param  Collection<int, object>  $groupItems
     * @return Collection<int, User>
     */
    private function loadUsersForGroups(Collection $groupItems): Collection
    {
        $userIds = $groupItems
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');
    }

    private function buildVisitorKey(string $pagePath, mixed $userId): string
    {
        return $pagePath.'::'.($userId ?? 'anonymous');
    }
}
