<?php

namespace App\Services\CryptoInvestment;

use App\Models\PlatformSetting;
use App\Services\PlatformSettings\UpdatesPlatformSetting;
use Illuminate\Validation\ValidationException;

class UpdatesCryptoInvestmentProgramSettings
{
    public function __construct(
        private UpdatesPlatformSetting $updatesPlatformSetting,
        private ResolvesCryptoInvestmentProgramSettings $resolvesCryptoInvestmentProgramSettings,
        private FetchesCoinGeckoTopMarketCoins $fetchesCoinGeckoTopMarketCoins,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        if (array_key_exists('is_enabled', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_IS_ENABLED,
                filter_var($input['is_enabled'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            );
        }

        if (array_key_exists('term_days', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_TERM_DAYS,
                (string) max(1, (int) $input['term_days']),
            );
        }

        if (array_key_exists('minimum_amount_usd', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_MINIMUM_AMOUNT_USD,
                number_format((float) $input['minimum_amount_usd'], 2, '.', ''),
            );
        }

        if (array_key_exists('maximum_amount_usd', $input)) {
            $maximum = $input['maximum_amount_usd'];

            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_MAXIMUM_AMOUNT_USD,
                $maximum === null || $maximum === ''
                    ? 'null'
                    : number_format((float) $maximum, 2, '.', ''),
            );
        }

        if (array_key_exists('fee_type', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_FEE_TYPE,
                (string) $input['fee_type'],
            );
        }

        if (array_key_exists('fee_value', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_FEE_VALUE,
                number_format((float) $input['fee_value'], 4, '.', ''),
            );
        }

        if (array_key_exists('max_loss_enabled', $input)) {
            $enabled = filter_var($input['max_loss_enabled'], FILTER_VALIDATE_BOOLEAN);

            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_MAX_LOSS_ENABLED,
                $enabled ? '1' : '0',
            );

            if (! $enabled) {
                $this->updatesPlatformSetting->update(
                    PlatformSetting::CRYPTO_INVESTMENT_MAX_LOSS_PERCENT,
                    '50.00',
                );
            }
        }

        if (array_key_exists('max_loss_percent', $input) && $input['max_loss_percent'] !== null) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_MAX_LOSS_PERCENT,
                number_format((float) $input['max_loss_percent'], 2, '.', ''),
            );
        }

        if (array_key_exists('supported_asset_ids', $input)) {
            /** @var list<string> $assetIds */
            $assetIds = array_values(array_unique(array_map('strval', $input['supported_asset_ids'])));

            if ($assetIds === []) {
                throw ValidationException::withMessages([
                    'supported_asset_ids' => ['Select at least one supported crypto asset.'],
                ]);
            }

            $supportedAssets = [];

            foreach ($assetIds as $assetId) {
                $coin = $this->fetchesCoinGeckoTopMarketCoins->findTopThirtyCoinById($assetId);

                if ($coin === null) {
                    throw ValidationException::withMessages([
                        'supported_asset_ids' => ["{$assetId} is not in the current CoinGecko top 30."],
                    ]);
                }

                $supportedAssets[] = $coin;
            }

            $this->updatesPlatformSetting->update(
                PlatformSetting::CRYPTO_INVESTMENT_SUPPORTED_ASSETS,
                json_encode($supportedAssets, JSON_THROW_ON_ERROR),
            );
        }

        return $this->resolvesCryptoInvestmentProgramSettings->current();
    }
}
