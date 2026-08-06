<?php

namespace Database\Factories;

use App\Enums\WalletLedgerEntryType;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletLedgerEntry>
 */
class WalletLedgerEntryFactory extends Factory
{
    protected $model = WalletLedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = 100.0;

        return [
            'user_wallet_id' => UserWallet::factory(),
            'entry_type' => WalletLedgerEntryType::DepositCredit->value,
            'amount' => $amount,
            'balance_after' => $amount,
            'wallet_deposit_id' => null,
            'description' => 'Test ledger credit',
            'created_by_admin_id' => null,
            'created_at' => now(),
        ];
    }
}
