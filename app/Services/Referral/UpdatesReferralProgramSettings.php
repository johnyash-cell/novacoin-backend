<?php

namespace App\Services\Referral;

use App\Models\PlatformSetting;
use App\Services\PlatformSettings\UpdatesPlatformSetting;

class UpdatesReferralProgramSettings
{
    public function __construct(
        private UpdatesPlatformSetting $updatesPlatformSetting,
        private ResolvesReferralProgramSettings $resolvesReferralProgramSettings,
    ) {}

    /**
     * @param  array{reward_amount_usd?: float|int|string, payout_mode?: string}  $input
     * @return array{
     *     reward_amount_usd: string,
     *     payout_mode: string,
     *     payout_mode_label: string,
     *     allowed_payout_modes: list<array{value: string, label: string}>
     * }
     */
    public function update(array $input): array
    {
        if (array_key_exists('reward_amount_usd', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::REFERRAL_REWARD_AMOUNT_USD,
                number_format((float) $input['reward_amount_usd'], 2, '.', ''),
            );
        }

        if (array_key_exists('payout_mode', $input)) {
            $this->updatesPlatformSetting->update(
                PlatformSetting::REFERRAL_REWARD_PAYOUT_MODE,
                (string) $input['payout_mode'],
            );
        }

        return $this->resolvesReferralProgramSettings->current();
    }
}
