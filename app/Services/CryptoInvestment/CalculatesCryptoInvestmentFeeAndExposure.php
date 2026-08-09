<?php

namespace App\Services\CryptoInvestment;

use App\Enums\CryptoInvestmentFeeChargeSource;
use App\Enums\CryptoInvestmentFeeType;
use Illuminate\Validation\ValidationException;

class CalculatesCryptoInvestmentFeeAndExposure
{
    /**
     * @return array{
     *     fee_usd: float,
     *     committed_usd: float,
     *     total_debit_usd: float,
     *     max_loss_floor_usd: float|null
     * }
     */
    public function calculate(
        float $amountUsd,
        string $feeType,
        float $feeValue,
        string $feeChargeSource,
        bool $maxLossEnabled = false,
        ?float $maxLossPercent = null,
    ): array {
        if ($amountUsd <= 0) {
            throw ValidationException::withMessages([
                'amount_usd' => ['Investment amount must be greater than zero.'],
            ]);
        }

        $feeTypeEnum = CryptoInvestmentFeeType::tryFrom($feeType);
        $feeChargeSourceEnum = CryptoInvestmentFeeChargeSource::tryFrom($feeChargeSource);

        if ($feeTypeEnum === null) {
            throw ValidationException::withMessages([
                'fee_type' => ['Fee type is invalid.'],
            ]);
        }

        if ($feeChargeSourceEnum === null) {
            throw ValidationException::withMessages([
                'fee_charge_source' => ['Fee charge source is invalid.'],
            ]);
        }

        $feeUsd = match ($feeTypeEnum) {
            CryptoInvestmentFeeType::FixedUsd => round($feeValue, 2),
            CryptoInvestmentFeeType::Percent => round($amountUsd * ($feeValue / 100), 2),
        };

        if ($feeUsd < 0) {
            throw ValidationException::withMessages([
                'fee_value' => ['Fee must not be negative.'],
            ]);
        }

        if ($feeChargeSourceEnum === CryptoInvestmentFeeChargeSource::FromInvestAmount) {
            $committedUsd = round($amountUsd - $feeUsd, 2);
            $totalDebitUsd = round($amountUsd, 2);

            if ($committedUsd <= 0) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['After the fee, investment exposure must be greater than zero. Increase the amount or choose fee from wallet.'],
                ]);
            }
        } else {
            $committedUsd = round($amountUsd, 2);
            $totalDebitUsd = round($amountUsd + $feeUsd, 2);
        }

        $maxLossFloorUsd = null;

        if ($maxLossEnabled) {
            if ($maxLossPercent === null || $maxLossPercent <= 0 || $maxLossPercent > 50) {
                throw ValidationException::withMessages([
                    'max_loss_percent' => ['Max loss percent must be greater than 0 and at most 50 when max loss is enabled.'],
                ]);
            }

            $maxLossFloorUsd = round($committedUsd * (1 - ($maxLossPercent / 100)), 2);
            $maxLossFloorUsd = max(0.0, $maxLossFloorUsd);
        }

        return [
            'fee_usd' => $feeUsd,
            'committed_usd' => $committedUsd,
            'total_debit_usd' => $totalDebitUsd,
            'max_loss_floor_usd' => $maxLossFloorUsd,
        ];
    }
}
