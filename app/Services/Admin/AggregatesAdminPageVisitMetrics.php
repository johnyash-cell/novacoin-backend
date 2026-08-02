<?php

namespace App\Services\Admin;

use App\Models\UserPageVisitLog;
use Carbon\Carbon;

class AggregatesAdminPageVisitMetrics
{
    /**
     * @return array{
     *     total_visits: int,
     *     unique_visitors: int,
     *     today_visits: int,
     *     this_week_visits: int,
     * }
     */
    public function aggregate(): array
    {
        $todayStart = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();

        $authenticatedVisitorCount = UserPageVisitLog::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $anonymousVisitorCount = UserPageVisitLog::query()
            ->whereNull('user_id')
            ->whereNotNull('ip_address')
            ->distinct()
            ->count('ip_address');

        return [
            'total_visits' => UserPageVisitLog::query()->count(),
            'unique_visitors' => $authenticatedVisitorCount + $anonymousVisitorCount,
            'today_visits' => UserPageVisitLog::query()
                ->where('visited_at', '>=', $todayStart)
                ->count(),
            'this_week_visits' => UserPageVisitLog::query()
                ->where('visited_at', '>=', $weekStart)
                ->count(),
        ];
    }
}
