<?php

namespace App\Http\Resources;

use App\Models\ReferralRewardPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralRewardPayout
 */
class AdminReferralRewardPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'referrer_user' => $this->whenLoaded('referrerUser', fn () => [
                'id' => $this->referrerUser?->id,
                'first_name' => $this->referrerUser?->first_name,
                'last_name' => $this->referrerUser?->last_name,
                'email' => $this->referrerUser?->email,
                'referral_code' => $this->referrerUser?->referral_code,
            ]),
            'referred_user' => $this->whenLoaded('referredUser', fn () => [
                'id' => $this->referredUser?->id,
                'first_name' => $this->referredUser?->first_name,
                'last_name' => $this->referredUser?->last_name,
                'email' => $this->referredUser?->email,
                'referral_code' => $this->referredUser?->referral_code,
            ]),
            'wallet_deposit_id' => $this->wallet_deposit_id,
            'created_at' => $this->created_at,
        ];
    }
}
