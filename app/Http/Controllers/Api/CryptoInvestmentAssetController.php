<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CryptoAssetInvestQuoteRequest;
use App\Http\Requests\Api\InvestInCryptoAssetRequest;
use App\Http\Resources\CryptoInvestmentResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Services\CryptoInvestment\CalculatesCryptoInvestmentFeeAndExposure;
use App\Services\CryptoInvestment\DebitsUserWalletForCryptoAssetInvestment;
use App\Services\CryptoInvestment\FetchesCoinGeckoMarketSnapshotsForAssetIds;
use App\Services\CryptoInvestment\ResolvesCryptoInvestmentProgramSettings;
use App\Services\Wallet\FetchesCoinGeckoUsdAssetPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CryptoInvestmentAssetController extends Controller
{
    use RespondsWithApiEnvelope;

    public function settings(ResolvesCryptoInvestmentProgramSettings $resolvesCryptoInvestmentProgramSettings): JsonResponse
    {
        return $this->successResponse(
            message: 'Crypto investment settings fetched successfully',
            data: $resolvesCryptoInvestmentProgramSettings->currentForMember(),
        );
    }

    public function index(
        ResolvesCryptoInvestmentProgramSettings $resolvesCryptoInvestmentProgramSettings,
        FetchesCoinGeckoMarketSnapshotsForAssetIds $fetchesCoinGeckoMarketSnapshotsForAssetIds,
        FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
    ): JsonResponse {
        $settings = $resolvesCryptoInvestmentProgramSettings->currentForMember();
        $supportedAssets = $resolvesCryptoInvestmentProgramSettings->supportedAssets();
        $assetIds = array_column($supportedAssets, 'coingecko_asset_id');
        $marketSnapshots = $fetchesCoinGeckoMarketSnapshotsForAssetIds->fetchByAssetIds($assetIds);
        $assets = [];

        foreach ($supportedAssets as $asset) {
            $snapshot = $marketSnapshots[$asset['coingecko_asset_id']] ?? null;
            $currentPriceUsd = $snapshot['current_price_usd'] ?? null;

            // Fallback keeps invest truth available if markets enrichment missed this id.
            if ($currentPriceUsd === null) {
                try {
                    $currentPriceUsd = $fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit(
                        $asset['coingecko_asset_id'],
                    );
                } catch (Throwable) {
                    $currentPriceUsd = null;
                }
            }

            $assets[] = [
                ...$asset,
                'current_price_usd' => $currentPriceUsd,
                'price_change_percentage_24h' => $snapshot['price_change_percentage_24h'] ?? null,
                'image_url' => $snapshot['image_url'] ?? null,
                'can_invest' => $resolvesCryptoInvestmentProgramSettings->isEnabled() && $currentPriceUsd !== null,
            ];
        }

        return $this->successResponse(
            message: 'Crypto investment assets fetched successfully',
            data: [
                'is_enabled' => $settings['is_enabled'],
                'term_days' => $settings['term_days'],
                'minimum_amount_usd' => $settings['minimum_amount_usd'],
                'maximum_amount_usd' => $settings['maximum_amount_usd'],
                'fee_type' => $settings['fee_type'],
                'fee_value' => $settings['fee_value'],
                'max_loss_enabled' => $settings['max_loss_enabled'],
                'max_loss_percent' => $settings['max_loss_percent'],
                'assets' => $assets,
            ],
        );
    }

    public function investQuote(
        CryptoAssetInvestQuoteRequest $request,
        string $coingeckoAssetId,
        ResolvesCryptoInvestmentProgramSettings $resolvesCryptoInvestmentProgramSettings,
        CalculatesCryptoInvestmentFeeAndExposure $calculatesCryptoInvestmentFeeAndExposure,
        FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
    ): JsonResponse {
        $resolvesCryptoInvestmentProgramSettings->assertInvestingIsEnabled();
        $asset = $resolvesCryptoInvestmentProgramSettings->requireSupportedAsset($coingeckoAssetId);

        $validated = $request->validated();
        $amountUsd = (float) $validated['amount_usd'];
        $feeChargeSource = (string) $validated['fee_charge_source'];

        $exposure = $calculatesCryptoInvestmentFeeAndExposure->calculate(
            amountUsd: $amountUsd,
            feeType: $resolvesCryptoInvestmentProgramSettings->feeType(),
            feeValue: $resolvesCryptoInvestmentProgramSettings->feeValue(),
            feeChargeSource: $feeChargeSource,
            maxLossEnabled: $resolvesCryptoInvestmentProgramSettings->isMaxLossEnabled(),
            maxLossPercent: $resolvesCryptoInvestmentProgramSettings->maxLossPercent(),
        );

        $currentPriceUsd = $fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit(
            $asset['coingecko_asset_id'],
        );

        $estimatedUnits = $currentPriceUsd > 0
            ? $exposure['committed_usd'] / $currentPriceUsd
            : 0.0;

        return $this->successResponse(
            message: 'Crypto investment quote calculated successfully',
            data: [
                'coingecko_asset_id' => $asset['coingecko_asset_id'],
                'asset_symbol' => $asset['asset_symbol'],
                'asset_label' => $asset['asset_label'],
                'amount_usd' => $amountUsd,
                'fee_charge_source' => $feeChargeSource,
                'current_price_usd' => $currentPriceUsd,
                'fee_usd' => $exposure['fee_usd'],
                'committed_usd' => $exposure['committed_usd'],
                'total_debit_usd' => $exposure['total_debit_usd'],
                'max_loss_floor_usd' => $exposure['max_loss_floor_usd'],
                'estimated_units' => $estimatedUnits,
                'term_days' => $resolvesCryptoInvestmentProgramSettings->termDays(),
            ],
        );
    }

    public function invest(
        InvestInCryptoAssetRequest $request,
        string $coingeckoAssetId,
        DebitsUserWalletForCryptoAssetInvestment $debitsUserWalletForCryptoAssetInvestment,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();

        $cryptoInvestment = $debitsUserWalletForCryptoAssetInvestment->invest(
            user: $user,
            coingeckoAssetId: $coingeckoAssetId,
            amountUsd: (float) $validated['amount_usd'],
            feeChargeSource: (string) $validated['fee_charge_source'],
        );

        return $this->successResponse(
            message: 'Crypto investment placed successfully',
            data: (new CryptoInvestmentResource($cryptoInvestment))->resolve(),
            statusCode: 201,
        );
    }
}
