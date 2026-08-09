<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexCryptoInvestmentDailyValuationsRequest;
use App\Http\Requests\Api\IndexUserCryptoInvestmentsRequest;
use App\Http\Resources\CryptoInvestmentDailyValuationResource;
use App\Http\Resources\CryptoInvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\CryptoInvestment;
use App\Models\CryptoInvestmentDailyValuation;
use App\Models\User;
use App\Services\CryptoInvestment\ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CryptoInvestmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(
        IndexUserCryptoInvestmentsRequest $request,
        ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts $processesCryptoInvestmentDailyMarkToMarketAndPayouts,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $processesCryptoInvestmentDailyMarkToMarketAndPayouts->processForUser($user->id);

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;
        $statusFilter = $validated['status'] ?? null;

        $investmentsQuery = CryptoInvestment::query()
            ->forUser($user->id);

        if ($statusFilter !== null) {
            $investmentsQuery->withStoredStatus($statusFilter);
        }

        $investments = $investmentsQuery
            ->orderBy('started_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        $activeCount = CryptoInvestment::query()
            ->forUser($user->id)
            ->withStoredStatus(InvestmentStatus::Active->value)
            ->count();

        $endedCount = CryptoInvestment::query()
            ->forUser($user->id)
            ->withStoredStatus(InvestmentStatus::Ended->value)
            ->count();

        return $this->successResponse(
            message: 'Crypto investments fetched successfully',
            data: CryptoInvestmentResource::collection($investments->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $investments->currentPage(),
                    'per_page' => $investments->perPage(),
                    'total' => $investments->total(),
                    'last_page' => $investments->lastPage(),
                ],
                'filters' => [
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

    public function show(
        CryptoInvestment $cryptoInvestment,
        ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts $processesCryptoInvestmentDailyMarkToMarketAndPayouts,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($cryptoInvestment->user_id !== $user->id) {
            return $this->errorResponse(
                message: 'Crypto investment not found',
                statusCode: 404,
            );
        }

        $processesCryptoInvestmentDailyMarkToMarketAndPayouts->processInvestment($cryptoInvestment);
        $cryptoInvestment->refresh();

        return $this->successResponse(
            message: 'Crypto investment fetched successfully',
            data: (new CryptoInvestmentResource($cryptoInvestment))->resolve(),
        );
    }

    public function dailyValuations(
        CryptoInvestment $cryptoInvestment,
        IndexCryptoInvestmentDailyValuationsRequest $request,
        ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts $processesCryptoInvestmentDailyMarkToMarketAndPayouts,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($cryptoInvestment->user_id !== $user->id) {
            return $this->errorResponse(
                message: 'Crypto investment not found',
                statusCode: 404,
            );
        }

        $processesCryptoInvestmentDailyMarkToMarketAndPayouts->processInvestment($cryptoInvestment);

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $valuations = CryptoInvestmentDailyValuation::query()
            ->where('crypto_investment_id', $cryptoInvestment->id)
            ->orderBy('valuation_date', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Crypto investment daily valuations fetched successfully',
            data: CryptoInvestmentDailyValuationResource::collection($valuations->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $valuations->currentPage(),
                    'per_page' => $valuations->perPage(),
                    'total' => $valuations->total(),
                    'last_page' => $valuations->lastPage(),
                ],
                'filters' => [
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }
}
