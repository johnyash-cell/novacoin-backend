<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexUserInvestmentsRequest;
use App\Http\Resources\InvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(IndexUserInvestmentsRequest $request): JsonResponse
    {
        Investment::endAllDue();

        /** @var User $user */
        $user = Auth::guard('api')->user();

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

    public function show(Investment $investment): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($investment->user_id !== $user->id) {
            return $this->errorResponse(
                message: 'Investment not found',
                statusCode: 404,
            );
        }

        $investment->endIfDue();
        $investment->refresh();
        $investment->load('investmentPackage');

        return $this->successResponse(
            message: 'Investment fetched successfully',
            data: (new InvestmentResource($investment))->resolve(),
        );
    }
}
