<?php

namespace App\Services\Referral;

use App\Enums\ReferralRewardPayoutMode;
use App\Models\PlatformSetting;
use App\Services\PlatformSettings\ResolvesPlatformSetting;

class ResolvesReferralProgramSettings
{
    public function __construct(
        private ResolvesPlatformSetting $resolvesPlatformSetting,
    ) {}

    /**
     * @return array{
     *     reward_amount_usd: string,
     *     payout_mode: string,
     *     payout_mode_label: string,
     *     allowed_payout_modes: list<array{value: string, label: string}>
     * }
     */
    public function current(): array
    {
        $amount = $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::REFERRAL_REWARD_AMOUNT_USD,
            '10.00',
        );
        $modeValue = $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::REFERRAL_REWARD_PAYOUT_MODE,
            ReferralRewardPayoutMode::FirstApprovedDepositOnly->value,
        );
        $mode = ReferralRewardPayoutMode::tryFrom($modeValue);

        return [
            'reward_amount_usd' => number_format((float) $amount, 2, '.', ''),
            'payout_mode' => $mode?->value ?? $modeValue,
            'payout_mode_label' => $mode?->label()
                ?? ucfirst(str_replace('_', ' ', $modeValue)),
            'allowed_payout_modes' => ReferralRewardPayoutMode::options(),
        ];
    }

    public function rewardAmountUsd(): float
    {
        $amount = $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::REFERRAL_REWARD_AMOUNT_USD,
            '10.00',
        );

        return round((float) $amount, 2);
    }

    public function payoutMode(): ReferralRewardPayoutMode
    {
        $modeValue = $this->resolvesPlatformSetting->valueOrDefault(
            PlatformSetting::REFERRAL_REWARD_PAYOUT_MODE,
            ReferralRewardPayoutMode::FirstApprovedDepositOnly->value,
        );

        return ReferralRewardPayoutMode::tryFrom($modeValue)
            ?? ReferralRewardPayoutMode::FirstApprovedDepositOnly;
    }
}
