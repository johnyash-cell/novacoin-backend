<?php

namespace App\Services\Admin;

use App\Enums\WalletLedgerEntryType;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\ResolvesUserWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetsMemberWalletAvailableBalanceAbsolute
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
    ) {}

    /**
     * Set the member spendable wallet to an absolute USD balance (not a delta).
     *
     * @return array{previous_available_balance: float, current_available_balance: float, did_change: bool}
     */
    public function set(User $user, Admin $admin, float $availableBalanceUsd): array
    {
        $targetBalance = round($availableBalanceUsd, 2);

        return DB::transaction(function () use ($user, $admin, $targetBalance): array {
            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousBalance = round((float) $lockedWallet->available_balance, 2);

            // Same absolute value twice → no balance write and no ledger noise.
            if ($previousBalance === $targetBalance) {
                return [
                    'previous_available_balance' => $previousBalance,
                    'current_available_balance' => $previousBalance,
                    'did_change' => false,
                ];
            }

            $delta = round($targetBalance - $previousBalance, 2);

            $lockedWallet->forceFill([
                'available_balance' => $targetBalance,
                'currency_code' => 'USD',
            ])->save();

            // Ledger keeps spendable balance reconcilable; admin id is the audit actor.
            WalletLedgerEntry::query()->create([
                'user_wallet_id' => $lockedWallet->id,
                'entry_type' => WalletLedgerEntryType::AdminBalanceAdjustment->value,
                'amount' => $delta,
                'balance_after' => $targetBalance,
                'description' => sprintf(
                    'Admin set available balance from %s to %s USD',
                    number_format($previousBalance, 2, '.', ''),
                    number_format($targetBalance, 2, '.', ''),
                ),
                'created_by_admin_id' => $admin->id,
                'created_at' => now(),
            ]);

            Log::info('Admin set member wallet available balance', [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'user_wallet_id' => $lockedWallet->id,
                'previous_available_balance' => $previousBalance,
                'current_available_balance' => $targetBalance,
                'delta_usd' => $delta,
            ]);

            return [
                'previous_available_balance' => $previousBalance,
                'current_available_balance' => $targetBalance,
                'did_change' => true,
            ];
        });
    }
}
