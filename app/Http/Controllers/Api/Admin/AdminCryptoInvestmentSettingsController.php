<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateCryptoInvestmentSettingsRequest;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Services\CryptoInvestment\FetchesCoinGeckoTopMarketCoins;
use App\Services\CryptoInvestment\ResolvesCryptoInvestmentProgramSettings;
use App\Services\CryptoInvestment\UpdatesCryptoInvestmentProgramSettings;
use Illuminate\Http\JsonResponse;

class AdminCryptoInvestmentSettingsController extends Controller
{
    use RespondsWithApiEnvelope;

    public function show(ResolvesCryptoInvestmentProgramSettings $resolvesCryptoInvestmentProgramSettings): JsonResponse
    {
        return $this->successResponse(
            message: 'Crypto investment settings fetched successfully',
            data: $resolvesCryptoInvestmentProgramSettings->current(),
        );
    }

    public function update(
        UpdateCryptoInvestmentSettingsRequest $request,
        UpdatesCryptoInvestmentProgramSettings $updatesCryptoInvestmentProgramSettings,
    ): JsonResponse {
        $settings = $updatesCryptoInvestmentProgramSettings->update($request->validated());

        return $this->successResponse(
            message: 'Crypto investment settings updated successfully',
            data: $settings,
        );
    }

    public function coinOptions(FetchesCoinGeckoTopMarketCoins $fetchesCoinGeckoTopMarketCoins): JsonResponse
    {
        $options = $fetchesCoinGeckoTopMarketCoins->fetchTopThirty();

        return $this->successResponse(
            message: 'Crypto investment coin options retrieved successfully',
            data: [
                'options' => $options,
                'total' => count($options),
            ],
        );
    }
}
