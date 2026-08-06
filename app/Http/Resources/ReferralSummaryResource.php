<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * @property-read int $referred_users_count
 * @property-read float|string|null $total_referral_rewards_earned
 * @property-read array{
 *     reward_amount_usd: string,
 *     payout_mode: string,
 *     payout_mode_label: string,
 *     allowed_payout_modes: list<array{value: string, label: string}>
 * } $referral_program_settings
 */
class ReferralSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->referral_program_settings;

        return [
            'referral_code' => $this->referral_code,
            'referred_users_count' => (int) ($this->referred_users_count ?? 0),
            'total_rewards_earned_usd' => number_format(
                (float) ($this->total_referral_rewards_earned ?? 0),
                2,
                '.',
                '',
            ),
            'reward_amount_usd' => $settings['reward_amount_usd'],
            'payout_mode' => $settings['payout_mode'],
            'payout_mode_label' => $settings['payout_mode_label'],
        ];
    }
}
