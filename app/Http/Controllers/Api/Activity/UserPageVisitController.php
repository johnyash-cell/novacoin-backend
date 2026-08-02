<?php

namespace App\Http\Controllers\Api\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Activity\StoreUserPageVisitRequest;
use App\Http\Resources\UserPageVisitLogResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Services\Activity\RecordsUserPageVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserPageVisitController extends Controller
{
    use RespondsWithApiEnvelope;

    public function store(
        StoreUserPageVisitRequest $request,
        RecordsUserPageVisit $recordsUserPageVisit,
    ): JsonResponse {
        /** @var User|null $authenticatedUser */
        $authenticatedUser = Auth::guard('api')->user();

        $pageVisitLog = $recordsUserPageVisit->record(
            user: $authenticatedUser,
            validated: $request->validated(),
            request: $request,
        );

        return $this->successResponse(
            message: 'Page visit recorded successfully',
            data: (new UserPageVisitLogResource($pageVisitLog))->resolve(),
            statusCode: 201,
        );
    }
}
