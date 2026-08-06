<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAdminReferralRewardPayoutsRequest;
use App\Http\Resources\AdminReferralRewardPayoutResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\ReferralRewardPayout;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AdminReferralRewardPayoutController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'date_range',
                        'label' => 'Date Range',
                        'description' => 'Filter by payout date (start_date & end_date)',
                        'type' => 'date-range',
                        'options' => null,
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function index(IndexAdminReferralRewardPayoutsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $payouts = ReferralRewardPayout::query()
            ->with(['referrerUser', 'referredUser'])
            ->search($validated['search'] ?? null)
            ->when(
                filled($validated['referrer_user_id'] ?? null),
                fn ($query) => $query->where('referrer_user_id', $validated['referrer_user_id']),
            )
            ->when(
                filled($validated['referred_user_id'] ?? null),
                fn ($query) => $query->where('referred_user_id', $validated['referred_user_id']),
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

        return $this->successResponse(
            message: 'Referral reward payouts fetched successfully',
            data: AdminReferralRewardPayoutResource::collection($payouts->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $payouts->currentPage(),
                    'per_page' => $payouts->perPage(),
                    'total' => $payouts->total(),
                    'last_page' => $payouts->lastPage(),
                ],
                'filters' => [
                    'search' => $validated['search'] ?? null,
                    'sort_by' => $sortBy,
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                    'referrer_user_id' => $validated['referrer_user_id'] ?? null,
                    'referred_user_id' => $validated['referred_user_id'] ?? null,
                ],
            ],
        );
    }
}
