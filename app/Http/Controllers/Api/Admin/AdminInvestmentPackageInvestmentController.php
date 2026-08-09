<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAdminInvestmentPackageInvestmentsRequest;
use App\Http\Resources\AdminInvestmentPackageInvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Investment;
use App\Models\InvestmentPackage;
use App\Services\Investment\ProcessesInvestmentDailyAccrualAndMaturityPayouts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminInvestmentPackageInvestmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(InvestmentPackage $investmentPackage): JsonResponse
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
                        'description' => 'Filter package investments by active or ended status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                ],
                'total_available_filters' => 1,
                'investment_package_id' => $investmentPackage->id,
            ],
        );
    }

    public function index(
        IndexAdminInvestmentPackageInvestmentsRequest $request,
        InvestmentPackage $investmentPackage,
        ProcessesInvestmentDailyAccrualAndMaturityPayouts $processesInvestmentDailyAccrualAndMaturityPayouts,
    ): JsonResponse {
        $processesInvestmentDailyAccrualAndMaturityPayouts
            ->processForInvestmentPackage($investmentPackage->id);

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;
        $statusFilter = $validated['status'] ?? null;
        $search = $validated['search'] ?? null;

        $investmentsQuery = Investment::query()
            ->where('investment_package_id', $investmentPackage->id)
            ->with(['user']);

        if ($statusFilter !== null) {
            $investmentsQuery->withStoredStatus($statusFilter);
        }

        if (filled($search)) {
            $like = '%'.$search.'%';

            $investmentsQuery->whereHas('user', function (Builder $userQuery) use ($like): void {
                $userQuery
                    ->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);
            });
        }

        $investments = $investmentsQuery
            ->orderBy('started_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $baseSummaryQuery = Investment::query()
            ->where('investment_package_id', $investmentPackage->id);

        $activeCount = (clone $baseSummaryQuery)
            ->withStoredStatus(InvestmentStatus::Active->value)
            ->count();

        $endedCount = (clone $baseSummaryQuery)
            ->withStoredStatus(InvestmentStatus::Ended->value)
            ->count();

        return $this->successResponse(
            message: 'Investment package investments fetched successfully',
            data: AdminInvestmentPackageInvestmentResource::collection($investments->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $investments->currentPage(),
                    'per_page' => $investments->perPage(),
                    'total' => $investments->total(),
                    'last_page' => $investments->lastPage(),
                ],
                'filters' => [
                    'investment_package_id' => $investmentPackage->id,
                    'search' => $search,
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
