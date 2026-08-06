<?php

namespace App\Services\Wallet;

use App\Enums\WalletLedgerEntryType;
use App\Enums\WalletWithdrawalStatus;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeclinesWalletWithdrawal
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
    ) {}

    public function decline(
        WalletWithdrawal $walletWithdrawal,
        Admin $admin,
        ?string $declineReason = null,
    ): WalletWithdrawal {
        return DB::transaction(function () use ($walletWithdrawal, $admin, $declineReason): WalletWithdrawal {
            /** @var WalletWithdrawal $lockedWithdrawal */
            $lockedWithdrawal = WalletWithdrawal::query()
                ->whereKey($walletWithdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWithdrawal->status === WalletWithdrawalStatus::Declined->value) {
                return $lockedWithdrawal->fresh(['user', 'platformCryptoWallet', 'reviewedByAdmin']) ?? $lockedWithdrawal;
            }

            if ($lockedWithdrawal->status === WalletWithdrawalStatus::Approved->value) {
                throw new RuntimeException('Approved withdrawals cannot be declined.');
            }

            if ($lockedWithdrawal->status !== WalletWithdrawalStatus::PendingApproval->value) {
                throw new RuntimeException('Only pending withdrawals can be declined.');
            }

            $user = User::query()->findOrFail($lockedWithdrawal->user_id);
            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $refundAmount = (float) $lockedWithdrawal->usd_amount;
            $balanceAfter = round((float) $lockedWallet->available_balance + $refundAmount, 2);

            $lockedWallet->forceFill([
                'available_balance' => $balanceAfter,
            ])->save();

            WalletLedgerEntry::query()->create([
                'user_wallet_id' => $lockedWallet->id,
                'entry_type' => WalletLedgerEntryType::WithdrawalRefundCredit->value,
                'amount' => $refundAmount,
                'balance_after' => $balanceAfter,
                'wallet_withdrawal_id' => $lockedWithdrawal->id,
                'description' => 'Withdrawal declined — held funds returned to wallet',
                'created_by_admin_id' => $admin->id,
                'created_at' => now(),
            ]);

            $lockedWithdrawal->forceFill([
                'status' => WalletWithdrawalStatus::Declined->value,
                'decline_reason' => $declineReason,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'outbound_transaction_reference' => null,
            ])->save();

            return $lockedWithdrawal->fresh(['user', 'platformCryptoWallet', 'reviewedByAdmin']) ?? $lockedWithdrawal;
        });
    }
}
