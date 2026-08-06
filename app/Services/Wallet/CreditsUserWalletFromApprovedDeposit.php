<?php

namespace App\Services\Wallet;

use App\Enums\WalletDepositStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletDeposit;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditsUserWalletFromApprovedDeposit
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
    ) {}

    public function approve(WalletDeposit $walletDeposit, Admin $admin): WalletDeposit
    {
        return DB::transaction(function () use ($walletDeposit, $admin): WalletDeposit {
            /** @var WalletDeposit $lockedDeposit */
            $lockedDeposit = WalletDeposit::query()
                ->whereKey($walletDeposit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDeposit->status === WalletDepositStatus::Approved->value) {
                return $lockedDeposit->fresh(['user', 'platformCryptoWallet', 'reviewedByAdmin']) ?? $lockedDeposit;
            }

            // if ($lockedDeposit->status !== WalletDepositStatus::PendingApproval->value) {
            //     throw new RuntimeException('Only pending deposits can be approved.');
            // }

            $user = User::query()->findOrFail($lockedDeposit->user_id);
            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $creditAmount = (float) $lockedDeposit->usd_amount;
            $balanceAfter = round((float) $lockedWallet->available_balance + $creditAmount, 2);

            $lockedWallet->forceFill([
                'available_balance' => $balanceAfter,
            ])->save();

            WalletLedgerEntry::query()->create([
                'user_wallet_id' => $lockedWallet->id,
                'entry_type' => WalletLedgerEntryType::DepositCredit->value,
                'amount' => $creditAmount,
                'balance_after' => $balanceAfter,
                'wallet_deposit_id' => $lockedDeposit->id,
                'description' => 'Deposit approved and credited to wallet',
                'created_by_admin_id' => $admin->id,
                'created_at' => now(),
            ]);

            $lockedDeposit->forceFill([
                'status' => WalletDepositStatus::Approved->value,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'decline_reason' => null,
            ])->save();

            return $lockedDeposit->fresh(['user', 'platformCryptoWallet', 'reviewedByAdmin']) ?? $lockedDeposit;
        });
    }
}
