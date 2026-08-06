<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateReferralSettingsRequest;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Services\Referral\ResolvesReferralProgramSettings;
use App\Services\Referral\UpdatesReferralProgramSettings;
use Illuminate\Http\JsonResponse;

class AdminReferralSettingsController extends Controller
{
    use RespondsWithApiEnvelope;

    public function show(ResolvesReferralProgramSettings $resolvesReferralProgramSettings): JsonResponse
    {
        return $this->successResponse(
            message: 'Referral settings fetched successfully',
            data: $resolvesReferralProgramSettings->current(),
        );
    }

    public function update(
        UpdateReferralSettingsRequest $request,
        UpdatesReferralProgramSettings $updatesReferralProgramSettings,
    ): JsonResponse {
        $settings = $updatesReferralProgramSettings->update($request->validated());

        return $this->successResponse(
            message: 'Referral settings updated successfully',
            data: $settings,
        );
    }
}
