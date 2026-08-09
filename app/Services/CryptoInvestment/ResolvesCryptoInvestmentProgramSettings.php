<?php

namespace App\Services\CryptoInvestment;

use App\Enums\CryptoInvestmentFeeChargeSource;
use App\Enums\CryptoInvestmentFeeType;
use App\Models\PlatformSetting;
use App\Services\PlatformSettings\ResolvesPlatformSetting;
use Illuminate\Validation\ValidationException;

class ResolvesCryptoInvestmentProgramSettings
{
    public function __construct(
        private ResolvesPlatformSetting $resolvesPlatformSetting,
    ) {}

    /**
     * @return array{
     *     is_enabled: bool,
     *     term_days: int,
     *     minimum_amount_usd: string,
     *     maximum_amount_usd: string|null,
     *     fee_type: string,
     *     fee_type_label: string,
     *     fee_value: string,
     *     max_loss_enabled: bool,
     *     max_loss_percent: string|null,
     *     supported_assets: list<array{coingecko_asset_id: string, asset_symbol: string, asset_label: string}>,
     *     allowed_fee_types: list<array{value: string, label: string}>,
     *     allowed_fee_charge_sources: list<array{value: string, label: string}>
     * }
     */
    public function current(): array
    {
        $feeTypeValue = $this->feeType();
        $feeType = CryptoInvestmentFeeType::tryFrom($feeTypeValue);
        $maxLossEnabled = $this->isMaxLossEnabled();

        return [
            'is_enabled' => $this->isEnabled(),
            'term_days' => $this->termDays(),
            'minimum_amount_usd' => number_format($this->minimumAmountUsd(), 2, '.', ''),
            'maximum_amount_usd' => $this->maximumAmountUsd() !== null
                ? number_format($this->maximumAmountUsd(), 2, '.', '')
                : null,
            'fee_type' => $feeTypeValue,
            'fee_type_label' => $feeType?->label()
                ?? ucfirst(str_replace('_', ' ', $feeTypeValue)),
            'fee_value' => number_format($this->feeValue(), 4, '.', ''),
            'max_loss_enabled' => $maxLossEnabled,
            'max_loss_percent' => $maxLossEnabled
                ? number_format($this->maxLossPercent() ?? 0, 2, '.', '')
                : null,
            'supported_assets' => $this->supportedAssets(),
            'allowed_fee_types' => collect(CryptoInvestmentFeeType::cases())
                ->map(fn (CryptoInvestmentFeeType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->all(),
            'allowed_fee_charge_sources' => collect(CryptoInvestmentFeeChargeSource::cases())
                ->map(fn (CryptoInvestmentFeeChargeSource $source): array => [
                    'value' => $source->value,
                    'label' => $source->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Member-facing subset (no admin option catalogs).
     *
     * @return array<string, mixed>
     */
    public function currentForMember(): array
    {
        $settings = $this->current();
        unset($settings['allowed_fee_types']);

        return $settings;
    }

    public function isEnabled(): bool
    {
        return $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_IS_ENABLED,
            '1',
        ) === '1';
    }

    public function termDays(): int
    {
        return max(1, (int) $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_TERM_DAYS,
            '30',
        ));
    }

    public function minimumAmountUsd(): float
    {
        return round((float) $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_MINIMUM_AMOUNT_USD,
            '50.00',
        ), 2);
    }

    public function maximumAmountUsd(): ?float
    {
        $value = $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_MAXIMUM_AMOUNT_USD,
            '100000.00',
        );

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return round((float) $value, 2);
    }

    public function feeType(): string
    {
        return $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_FEE_TYPE,
            CryptoInvestmentFeeType::Percent->value,
        );
    }

    public function feeValue(): float
    {
        return (float) $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_FEE_VALUE,
            '1.0000',
        );
    }

    public function isMaxLossEnabled(): bool
    {
        return $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_MAX_LOSS_ENABLED,
            '1',
        ) === '1';
    }

    public function maxLossPercent(): ?float
    {
        if (! $this->isMaxLossEnabled()) {
            return null;
        }

        return (float) $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_MAX_LOSS_PERCENT,
            '50.00',
        );
    }

    /**
     * @return list<array{coingecko_asset_id: string, asset_symbol: string, asset_label: string}>
     */
    public function supportedAssets(): array
    {
        $raw = $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::CRYPTO_INVESTMENT_SUPPORTED_ASSETS,
            '[]',
        );

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $assets = [];

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['coingecko_asset_id']) ? (string) $row['coingecko_asset_id'] : '';
            $symbol = isset($row['asset_symbol']) ? (string) $row['asset_symbol'] : '';
            $label = isset($row['asset_label']) ? (string) $row['asset_label'] : '';

            if ($id === '' || $symbol === '' || $label === '') {
                continue;
            }

            $assets[] = [
                'coingecko_asset_id' => $id,
                'asset_symbol' => strtoupper($symbol),
                'asset_label' => $label,
            ];
        }

        return $assets;
    }

    /**
     * @return array{coingecko_asset_id: string, asset_symbol: string, asset_label: string}
     */
    public function requireSupportedAsset(string $coingeckoAssetId): array
    {
        foreach ($this->supportedAssets() as $asset) {
            if ($asset['coingecko_asset_id'] === $coingeckoAssetId) {
                return $asset;
            }
        }

        throw ValidationException::withMessages([
            'coingecko_asset_id' => ['This crypto asset is not enabled for investment.'],
        ]);
    }

    public function assertInvestingIsEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'crypto_investment' => ['Crypto investment is currently disabled.'],
            ]);
        }
    }
}
