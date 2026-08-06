<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAdminUserInvestmentsRequest;
use App\Http\Resources\InvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserInvestmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        $statusOptions = collect(InvestmentStatus::cases())
            ->map(fn (InvestmentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'description' => 'Filter investments by active or ended status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function index(IndexAdminUserInvestmentsRequest $request, User $user): JsonResponse
    {
        Investment::endAllDue();

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;
        $statusFilter = $validated['status'] ?? null;

        $investmentsQuery = Investment::query()
            ->forUser($user->id)
            ->with('investmentPackage');

        if ($statusFilter !== null) {
            $investmentsQuery->withStoredStatus($statusFilter);
        }

        $investments = $investmentsQuery
            ->orderBy('started_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $activeCount = Investment::query()
            ->forUser($user->id)
            ->withStoredStatus(InvestmentStatus::Active->value)
            ->count();

        $endedCount = Investment::query()
            ->forUser($user->id)
            ->withStoredStatus(InvestmentStatus::Ended->value)
            ->count();

        return $this->successResponse(
            message: 'User investments fetched successfully',
            data: InvestmentResource::collection($investments->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $investments->currentPage(),
                    'per_page' => $investments->perPage(),
                    'total' => $investments->total(),
                    'last_page' => $investments->lastPage(),
                ],
                'filters' => [
                    'user_id' => $user->id,
                    'status' => $statusFilter,
                    'sort_by' => $sortBy,
                ],
                'summary' => [
                    'active' => $activeCount,
                    'ended' => $endedCount,
                    'total' => $activeCount + $endedCount,
                ],
            ],
        );
    }
}
