<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexReferredUsersRequest;
use App\Http\Resources\ReferralSummaryResource;
use App\Http\Resources\ReferredUserResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Services\Referral\ResolvesReferralProgramSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    use RespondsWithApiEnvelope;

    public function show(ResolvesReferralProgramSettings $resolvesReferralProgramSettings): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $user->loadCount('referredUsers');
        $user->setAttribute(
            'total_referral_rewards_earned',
            (float) $user->referralRewardPayoutsAsReferrer()->sum('amount'),
        );
        $user->setAttribute(
            'referral_program_settings',
            $resolvesReferralProgramSettings->current(),
        );

        return $this->successResponse(
            message: 'Referral details fetched successfully',
            data: (new ReferralSummaryResource($user))->resolve(),
        );
    }

    public function referredUsers(IndexReferredUsersRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $referredUsers = User::query()
            ->where('referred_by_user_id', $user->id)
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Referred users fetched successfully',
            data: ReferredUserResource::collection($referredUsers->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $referredUsers->currentPage(),
                    'per_page' => $referredUsers->perPage(),
                    'total' => $referredUsers->total(),
                    'last_page' => $referredUsers->lastPage(),
                ],
                'filters' => [
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }
}
