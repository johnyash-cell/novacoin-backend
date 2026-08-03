<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexInvestmentPackagesRequest;
use App\Http\Requests\Api\Admin\StoreInvestmentPackageRequest;
use App\Http\Requests\Api\Admin\UpdateInvestmentPackageAvailabilityStatusRequest;
use App\Http\Requests\Api\Admin\UpdateInvestmentPackageFeaturedRequest;
use App\Http\Requests\Api\Admin\UpdateInvestmentPackageRequest;
use App\Http\Resources\InvestmentPackageResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\InvestmentPackage;
use Illuminate\Http\JsonResponse;

class AdminInvestmentPackageController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        $availabilityStatusLabels = collect(InvestmentPackageAvailabilityStatus::cases())
            ->mapWithKeys(fn (InvestmentPackageAvailabilityStatus $status) => [$status->value => $status->label()])
            ->all();

        $riskLevelLabels = collect(InvestmentPackageRiskLevel::cases())
            ->mapWithKeys(fn (InvestmentPackageRiskLevel $riskLevel) => [$riskLevel->value => $riskLevel->label()])
            ->all();

        $distinctAvailabilityStatuses = InvestmentPackage::query()
            ->whereNotNull('availability_status')
            ->distinct()
            ->pluck('availability_status')
            ->all();

        $distinctRiskLevels = InvestmentPackage::query()
            ->whereNotNull('risk_level')
            ->distinct()
            ->pluck('risk_level')
            ->all();

        $availabilityStatusValues = $distinctAvailabilityStatuses !== []
            ? $distinctAvailabilityStatuses
            : InvestmentPackageAvailabilityStatus::values();

        $riskLevelValues = $distinctRiskLevels !== []
            ? $distinctRiskLevels
            : InvestmentPackageRiskLevel::values();

        $availabilityStatusOptions = [];
        foreach ($availabilityStatusValues as $statusValue) {
            $availabilityStatusOptions[] = [
                'value' => $statusValue,
                'label' => $availabilityStatusLabels[$statusValue]
                    ?? ucfirst(str_replace('_', ' ', (string) $statusValue)),
            ];
        }

        $riskLevelOptions = [];
        foreach ($riskLevelValues as $riskLevelValue) {
            $riskLevelOptions[] = [
                'value' => $riskLevelValue,
                'label' => $riskLevelLabels[$riskLevelValue]
                    ?? ucfirst(str_replace('_', ' ', (string) $riskLevelValue)),
            ];
        }

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'availability_status',
                        'label' => 'Availability',
                        'description' => 'Filter by availability status',
                        'type' => 'single-select',
                        'options' => $availabilityStatusOptions,
                    ],
                    [
                        'key' => 'risk_level',
                        'label' => 'Risk',
                        'description' => 'Filter by risk level',
                        'type' => 'single-select',
                        'options' => $riskLevelOptions,
                    ],
                    [
                        'key' => 'is_featured',
                        'label' => 'Featured',
                        'description' => 'Filter by featured flag',
                        'type' => 'single-select',
                        'options' => [
                            ['value' => 'true', 'label' => 'Featured'],
                            ['value' => 'false', 'label' => 'Not featured'],
                        ],
                    ],
                ],
                'total_available_filters' => 3,
            ],
        );
    }

    public function index(IndexInvestmentPackagesRequest $request): JsonResponse
    {
        InvestmentPackage::expireAllDue();

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $packages = InvestmentPackage::query()
            ->search($validated['search'] ?? null)
            ->when(
                filled($validated['availability_status'] ?? null),
                fn ($query) => $query->where('availability_status', $validated['availability_status']),
            )
            ->when(
                filled($validated['risk_level'] ?? null),
                fn ($query) => $query->where('risk_level', $validated['risk_level']),
            )
            ->when(
                array_key_exists('is_featured', $validated) && $validated['is_featured'] !== null,
                fn ($query) => $query->where('is_featured', $validated['is_featured']),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $statusCounts = InvestmentPackage::query()
            ->selectRaw('availability_status, COUNT(*) as aggregate_count')
            ->groupBy('availability_status')
            ->pluck('aggregate_count', 'availability_status');

        return $this->successResponse(
            message: 'Investment packages fetched successfully',
            data: InvestmentPackageResource::collection($packages->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $packages->currentPage(),
                    'per_page' => $packages->perPage(),
                    'total' => $packages->total(),
                    'last_page' => $packages->lastPage(),
                ],
                'filters' => [
                    'search' => $validated['search'] ?? null,
                    'availability_status' => $validated['availability_status'] ?? null,
                    'risk_level' => $validated['risk_level'] ?? null,
                    'is_featured' => $validated['is_featured'] ?? null,
                    'sort_by' => $sortBy,
                ],
                'summary' => [
                    'open' => (int) ($statusCounts[InvestmentPackageAvailabilityStatus::Open->value] ?? 0),
                    'limited' => (int) ($statusCounts[InvestmentPackageAvailabilityStatus::Limited->value] ?? 0),
                    'full' => (int) ($statusCounts[InvestmentPackageAvailabilityStatus::Full->value] ?? 0),
                    'expired' => (int) ($statusCounts[InvestmentPackageAvailabilityStatus::Expired->value] ?? 0),
                    'total' => (int) InvestmentPackage::query()->count(),
                ],
            ],
        );
    }

    public function store(StoreInvestmentPackageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['is_featured'] = $validated['is_featured'] ?? false;
        $validated['highlights'] = $validated['highlights'] ?? null;

        $investmentPackage = InvestmentPackage::query()->create($validated);

        return $this->successResponse(
            message: 'Investment package created successfully',
            data: (new InvestmentPackageResource($investmentPackage))->resolve(),
            statusCode: 201,
        );
    }

    public function show(InvestmentPackage $investmentPackage): JsonResponse
    {
        $investmentPackage->expireIfDue();
        $investmentPackage->refresh();

        return $this->successResponse(
            message: 'Investment package fetched successfully',
            data: (new InvestmentPackageResource($investmentPackage))->resolve(),
        );
    }

    public function update(
        UpdateInvestmentPackageRequest $request,
        InvestmentPackage $investmentPackage,
    ): JsonResponse {
        $investmentPackage->expireIfDue();
        $investmentPackage->refresh();

        $investmentPackage->update($request->validated());

        return $this->successResponse(
            message: 'Investment package updated successfully',
            data: (new InvestmentPackageResource($investmentPackage->fresh()))->resolve(),
        );
    }

    public function destroy(InvestmentPackage $investmentPackage): JsonResponse
    {
        $investmentPackage->delete();

        return $this->successResponse(
            message: 'Investment package deleted successfully',
            data: null,
        );
    }

    public function updateAvailabilityStatus(
        UpdateInvestmentPackageAvailabilityStatusRequest $request,
        InvestmentPackage $investmentPackage,
    ): JsonResponse {
        $investmentPackage->expireIfDue();
        $investmentPackage->refresh();

        $investmentPackage->update([
            'availability_status' => $request->validated('availability_status'),
        ]);

        return $this->successResponse(
            message: 'Investment package availability updated successfully',
            data: (new InvestmentPackageResource($investmentPackage->fresh()))->resolve(),
        );
    }

    public function updateFeatured(
        UpdateInvestmentPackageFeaturedRequest $request,
        InvestmentPackage $investmentPackage,
    ): JsonResponse {
        $investmentPackage->expireIfDue();
        $investmentPackage->refresh();

        $investmentPackage->update([
            'is_featured' => $request->validated('is_featured'),
        ]);

        return $this->successResponse(
            message: 'Investment package featured flag updated successfully',
            data: (new InvestmentPackageResource($investmentPackage->fresh()))->resolve(),
        );
    }
}
