<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAdminCryptoInvestmentsRequest;
use App\Http\Resources\AdminCryptoInvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\CryptoInvestment;
use App\Services\CryptoInvestment\ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminCryptoInvestmentController extends Controller
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

        $assetOptions = CryptoInvestment::query()
            ->select(['coingecko_asset_id', 'asset_symbol', 'asset_label'])
            ->distinct()
            ->orderBy('asset_label')
            ->get()
            ->map(fn (CryptoInvestment $holding): array => [
                'value' => $holding->coingecko_asset_id,
                'label' => $holding->asset_label.' ('.$holding->asset_symbol.')',
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
                        'description' => 'Filter holdings by active or ended status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                    [
                        'key' => 'coingecko_asset_id',
                        'label' => 'Asset',
                        'description' => 'Filter holdings by crypto asset',
                        'type' => 'single-select',
                        'options' => $assetOptions,
                    ],
                ],
                'total_available_filters' => 2,
            ],
        );
    }

    public function index(
        IndexAdminCryptoInvestmentsRequest $request,
        ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts $processesCryptoInvestmentDailyMarkToMarketAndPayouts,
    ): JsonResponse {
        $processesCryptoInvestmentDailyMarkToMarketAndPayouts->processAll();

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;
        $statusFilter = $validated['status'] ?? null;
        $search = $validated['search'] ?? null;
        $assetId = $validated['coingecko_asset_id'] ?? null;

        $investmentsQuery = CryptoInvestment::query()->with(['user']);

        if ($statusFilter !== null) {
            $investmentsQuery->withStoredStatus($statusFilter);
        }

        if (filled($assetId)) {
            $investmentsQuery->where('coingecko_asset_id', $assetId);
        }

        if (filled($search)) {
            $like = '%'.$search.'%';

            $investmentsQuery->where(function (Builder $query) use ($like): void {
                $query
                    ->where('asset_symbol', 'like', $like)
                    ->orWhere('asset_label', 'like', $like)
                    ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                        $userQuery
                            ->where('email', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        $investments = $investmentsQuery
            ->orderBy('started_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $activeCount = CryptoInvestment::query()
            ->withStoredStatus(InvestmentStatus::Active->value)
            ->count();

        $endedCount = CryptoInvestment::query()
            ->withStoredStatus(InvestmentStatus::Ended->value)
            ->count();

        return $this->successResponse(
            message: 'Crypto investments fetched successfully',
            data: AdminCryptoInvestmentResource::collection($investments->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $investments->currentPage(),
                    'per_page' => $investments->perPage(),
                    'total' => $investments->total(),
                    'last_page' => $investments->lastPage(),
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $statusFilter,
                    'coingecko_asset_id' => $assetId,
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
