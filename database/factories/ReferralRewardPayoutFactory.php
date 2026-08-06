<?php

namespace Database\Factories;

use App\Models\ReferralRewardPayout;
use App\Models\User;
use App\Models\WalletDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralRewardPayout>
 */
class ReferralRewardPayoutFactory extends Factory
{
    protected $model = ReferralRewardPayout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_user_id' => User::factory(),
            'referred_user_id' => User::factory(),
            'wallet_deposit_id' => WalletDeposit::factory(),
            'amount' => 10.00,
            'created_at' => now(),
        ];
    }
}
