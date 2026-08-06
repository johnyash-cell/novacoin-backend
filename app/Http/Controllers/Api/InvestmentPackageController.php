<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexMemberInvestmentPackagesRequest;
use App\Http\Requests\Api\InvestInInvestmentPackageRequest;
use App\Http\Resources\InvestmentResource;
use App\Http\Resources\MemberInvestmentPackageResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\InvestmentPackage;
use App\Models\User;
use App\Services\Investment\DebitsUserWalletForPackageInvestment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InvestmentPackageController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(IndexMemberInvestmentPackagesRequest $request): JsonResponse
    {
        InvestmentPackage::expireAllDue();

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $packages = InvestmentPackage::query()
            ->search($validated['search'] ?? null)
            ->orderByDesc('is_featured')
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $joinableCount = InvestmentPackage::query()
            ->where('availability_status', '!=', InvestmentPackageAvailabilityStatus::Expired->value)
            ->whereIn('availability_status', [
                InvestmentPackageAvailabilityStatus::Open->value,
                InvestmentPackageAvailabilityStatus::Limited->value,
            ])
            ->whereColumn('joined_count', '<', 'max_participants')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        return $this->successResponse(
            message: 'Investment packages fetched successfully',
            data: MemberInvestmentPackageResource::collection($packages->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $packages->currentPage(),
                    'per_page' => $packages->perPage(),
                    'total' => $packages->total(),
                    'last_page' => $packages->lastPage(),
                ],
                'filters' => [
                    'search' => $validated['search'] ?? null,
                    'sort_by' => $sortBy,
                ],
                'summary' => [
                    'total' => (int) InvestmentPackage::query()->count(),
                    'joinable' => $joinableCount,
                    'expired' => (int) InvestmentPackage::query()
                        ->where('availability_status', InvestmentPackageAvailabilityStatus::Expired->value)
                        ->count(),
                ],
            ],
        );
    }

    public function show(InvestmentPackage $investmentPackage): JsonResponse
    {
        $investmentPackage->expireIfDue();
        $investmentPackage->refresh();

        return $this->successResponse(
            message: 'Investment package fetched successfully',
            data: (new MemberInvestmentPackageResource($investmentPackage))->resolve(),
        );
    }

    public function invest(
        InvestInInvestmentPackageRequest $request,
        InvestmentPackage $investmentPackage,
        DebitsUserWalletForPackageInvestment $debitsUserWalletForPackageInvestment,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $investment = $debitsUserWalletForPackageInvestment->invest(
            user: $user,
            investmentPackage: $investmentPackage,
            amountUsd: (float) $request->validated('amount_usd'),
        );

        return $this->successResponse(
            message: 'Investment placed successfully',
            data: (new InvestmentResource($investment))->resolve(),
            statusCode: 201,
        );
    }
}
