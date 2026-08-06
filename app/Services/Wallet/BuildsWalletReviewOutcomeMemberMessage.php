<?php

namespace App\Services\Wallet;

use App\Models\WalletDeposit;
use App\Models\WalletWithdrawal;

class BuildsWalletReviewOutcomeMemberMessage
{
    /**
     * @return array{title: string, message: string}
     */
    public function forApprovedDeposit(WalletDeposit $walletDeposit): array
    {
        $amount = $this->formatUsd((float) $walletDeposit->usd_amount);
        $reference = $walletDeposit->reference_number ?? '#'.$walletDeposit->id;

        return [
            'title' => 'Deposit approved',
            'message' => "Your deposit of {$amount} ({$reference}) has been approved and credited to your NovaCoin wallet.",
        ];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function forDeclinedDeposit(WalletDeposit $walletDeposit): array
    {
        $amount = $this->formatUsd((float) $walletDeposit->usd_amount);
        $reference = $walletDeposit->reference_number ?? '#'.$walletDeposit->id;
        $message = "Your deposit of {$amount} ({$reference}) was declined.";

        if (filled($walletDeposit->decline_reason)) {
            $message .= ' Reason: '.$walletDeposit->decline_reason;
        }

        return [
            'title' => 'Deposit declined',
            'message' => $message,
        ];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function forApprovedWithdrawal(WalletWithdrawal $walletWithdrawal): array
    {
        $amount = $this->formatUsd((float) $walletWithdrawal->usd_amount);
        $reference = $walletWithdrawal->reference_number ?? '#'.$walletWithdrawal->id;

        return [
            'title' => 'Withdrawal approved',
            'message' => "Your withdrawal of {$amount} ({$reference}) has been approved. Funds were sent to your destination wallet address.",
        ];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function forDeclinedWithdrawal(WalletWithdrawal $walletWithdrawal): array
    {
        $amount = $this->formatUsd((float) $walletWithdrawal->usd_amount);
        $reference = $walletWithdrawal->reference_number ?? '#'.$walletWithdrawal->id;
        $message = "Your withdrawal of {$amount} ({$reference}) was declined. The held amount has been returned to your wallet.";

        if (filled($walletWithdrawal->decline_reason)) {
            $message .= ' Reason: '.$walletWithdrawal->decline_reason;
        }

        return [
            'title' => 'Withdrawal declined',
            'message' => $message,
        ];
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
