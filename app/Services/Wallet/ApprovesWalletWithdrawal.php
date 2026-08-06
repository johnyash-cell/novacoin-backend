<?php

namespace App\Services\Wallet;

use App\Enums\WalletWithdrawalStatus;
use App\Models\Admin;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovesWalletWithdrawal
{
    public function approve(
        WalletWithdrawal $walletWithdrawal,
        Admin $admin,
        ?string $outboundTransactionReference = null,
    ): WalletWithdrawal {
        return DB::transaction(function () use ($walletWithdrawal, $admin, $outboundTransactionReference): WalletWithdrawal {
            /** @var WalletWithdrawal $lockedWithdrawal */
            $lockedWithdrawal = WalletWithdrawal::query()
                ->whereKey($walletWithdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWithdrawal->status === WalletWithdrawalStatus::Approved->value) {
                return $lockedWithdrawal->fresh(['user', 'platformCryptoWallet', 'reviewedByAdmin']) ?? $lockedWithdrawal;
            }

            if ($lockedWithdrawal->status !== WalletWithdrawalStatus::PendingApproval->value) {
                throw new RuntimeException('Only pending withdrawals can be approved.');
            }

            // Balance already debited on request — approve only records that payout was sent.
            $lockedWithdrawal->forceFill([
                'status' => WalletWithdrawalStatus::Approved->value,
                'outbound_transaction_reference' => $outboundTransactionReference,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'decline_reason' => null,
            ])->save();

            return $lockedWithdrawal->fresh(['user', 'platformCryptoWallet', 'reviewedByAdmin']) ?? $lockedWithdrawal;
        });
    }
}
