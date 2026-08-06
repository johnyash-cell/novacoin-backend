<?php

namespace Database\Factories;

use App\Enums\WalletDepositStatus;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\WalletDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletDeposit>
 */
class WalletDepositFactory extends Factory
{
    protected $model = WalletDeposit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform_crypto_wallet_id' => PlatformCryptoWallet::factory(),
            'usd_amount' => 1000,
            'crypto_amount_expected' => 0.015,
            'conversion_rate_usd_per_unit' => 66666.66666667,
            'quoted_at' => now(),
            'asset_symbol' => 'BTC',
            'network_name' => 'Bitcoin',
            'wallet_address' => 'bc1qfactorytestaddress000000000000',
            'proof_image_path' => 'wallet-deposit-proofs/test-proof.png',
            'status' => WalletDepositStatus::PendingApproval->value,
            'decline_reason' => null,
            'reviewed_by_admin_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletDepositStatus::PendingApproval->value,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletDepositStatus::Approved->value,
            'reviewed_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletDepositStatus::Declined->value,
            'decline_reason' => 'Could not verify payment',
            'reviewed_at' => now(),
        ]);
    }
}
