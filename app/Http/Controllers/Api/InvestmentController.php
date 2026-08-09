<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexInvestmentDailyEarningLogsRequest;
use App\Http\Requests\Api\IndexUserInvestmentsRequest;
use App\Http\Resources\InvestmentDailyEarningLogResource;
use App\Http\Resources\InvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Investment;
use App\Models\InvestmentDailyEarningLog;
use App\Models\User;
use App\Services\Investment\ProcessesInvestmentDailyAccrualAndMaturityPayouts;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(
        IndexUserInvestmentsRequest $request,
        ProcessesInvestmentDailyAccrualAndMaturityPayouts $processesInvestmentDailyAccrualAndMaturityPayouts,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $processesInvestmentDailyAccrualAndMaturityPayouts->processForUser($user->id);

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;
        $statusFilter = $validated['status'] ?? null;

        $investmentsQuery = Investment::query()
            ->forUser($user->id)
            ->with(['investmentPackage', 'dailyEarningLogs']);

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
            message: 'Investments fetched successfully',
            data: InvestmentResource::collection($investments->items())->resolve(),
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
        Investment $investment,
        ProcessesInvestmentDailyAccrualAndMaturityPayouts $processesInvestmentDailyAccrualAndMaturityPayouts,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($investment->user_id !== $user->id) {
            return $this->errorResponse(
                message: 'Investment not found',
                statusCode: 404,
            );
        }

        $processesInvestmentDailyAccrualAndMaturityPayouts->processInvestment($investment);
        $investment->refresh();
        $investment->load(['investmentPackage', 'dailyEarningLogs']);

        return $this->successResponse(
            message: 'Investment fetched successfully',
            data: (new InvestmentResource($investment))->resolve(),
        );
    }

    public function dailyEarnings(
        Investment $investment,
        IndexInvestmentDailyEarningLogsRequest $request,
        ProcessesInvestmentDailyAccrualAndMaturityPayouts $processesInvestmentDailyAccrualAndMaturityPayouts,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($investment->user_id !== $user->id) {
            return $this->errorResponse(
                message: 'Investment not found',
                statusCode: 404,
            );
        }

        $processesInvestmentDailyAccrualAndMaturityPayouts->processInvestment($investment);

        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $logs = InvestmentDailyEarningLog::query()
            ->where('investment_id', $investment->id)
            ->orderBy('earning_date', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Investment daily earnings fetched successfully',
            data: InvestmentDailyEarningLogResource::collection($logs->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
                'filters' => [
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }
}
