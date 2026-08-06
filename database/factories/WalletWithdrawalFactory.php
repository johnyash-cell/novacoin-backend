<?php

namespace Database\Factories;

use App\Enums\WalletWithdrawalStatus;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\WalletWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletWithdrawal>
 */
class WalletWithdrawalFactory extends Factory
{
    protected $model = WalletWithdrawal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform_crypto_wallet_id' => PlatformCryptoWallet::factory()->availableForWithdrawal(),
            'usd_amount' => 500,
            'crypto_amount_expected' => 0.01,
            'conversion_rate_usd_per_unit' => 50000,
            'quoted_at' => now(),
            'asset_symbol' => 'BTC',
            'network_name' => 'Bitcoin',
            'destination_wallet_address' => 'bc1qmemberdestinationaddress0000001',
            'status' => WalletWithdrawalStatus::PendingApproval->value,
            'decline_reason' => null,
            'outbound_transaction_reference' => null,
            'reviewed_by_admin_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletWithdrawalStatus::PendingApproval->value,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletWithdrawalStatus::Approved->value,
            'reviewed_at' => now(),
            'outbound_transaction_reference' => 'txid-example-approved',
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletWithdrawalStatus::Declined->value,
            'decline_reason' => 'Could not verify destination address',
            'reviewed_at' => now(),
        ]);
    }
}
