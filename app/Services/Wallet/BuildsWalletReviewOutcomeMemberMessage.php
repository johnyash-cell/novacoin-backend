<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletWithdrawal;

class BuildsWalletReviewOutcomeMemberMessage
{
    /**
     * @return array{title: string, message: string}
     */
    public function forApprovedDeposit(User $user, WalletDeposit $walletDeposit): array
    {
        $name = $this->firstName($user);
        $amount = $this->formatUsd((float) $walletDeposit->usd_amount);
        $reference = $walletDeposit->reference_number ?? '#'.$walletDeposit->id;
        $asset = (string) ($walletDeposit->asset_symbol ?: 'crypto');
        $network = (string) ($walletDeposit->network_name ?: 'network');

        return [
            'title' => "{$name}, your deposit was approved",
            'message' => "Hi {$name}, your Fund Account deposit of {$amount} (ref {$reference}) on {$asset}/{$network} has been approved and credited to your NovaCoin wallet.",
        ];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function forDeclinedDeposit(User $user, WalletDeposit $walletDeposit): array
    {
        $name = $this->firstName($user);
        $amount = $this->formatUsd((float) $walletDeposit->usd_amount);
        $reference = $walletDeposit->reference_number ?? '#'.$walletDeposit->id;
        $message = "Hi {$name}, your Fund Account deposit of {$amount} (ref {$reference}) was declined.";

        if (filled($walletDeposit->decline_reason)) {
            $message .= ' Reason: '.$walletDeposit->decline_reason;
        }

        $message .= ' Your wallet balance was not credited.';

        return [
            'title' => "{$name}, your deposit was declined",
            'message' => $message,
        ];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function forApprovedWithdrawal(User $user, WalletWithdrawal $walletWithdrawal): array
    {
        $name = $this->firstName($user);
        $amount = $this->formatUsd((float) $walletWithdrawal->usd_amount);
        $reference = $walletWithdrawal->reference_number ?? '#'.$walletWithdrawal->id;
        $destination = (string) ($walletWithdrawal->destination_wallet_address ?: 'your destination wallet');
        $asset = (string) ($walletWithdrawal->asset_symbol ?: 'crypto');
        $network = (string) ($walletWithdrawal->network_name ?: 'network');

        return [
            'title' => "{$name}, your withdrawal was approved",
            'message' => "Hi {$name}, your withdrawal of {$amount} (ref {$reference}) on {$asset}/{$network} has been approved. Funds were sent to {$destination}.",
        ];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function forDeclinedWithdrawal(User $user, WalletWithdrawal $walletWithdrawal): array
    {
        $name = $this->firstName($user);
        $amount = $this->formatUsd((float) $walletWithdrawal->usd_amount);
        $reference = $walletWithdrawal->reference_number ?? '#'.$walletWithdrawal->id;
        $message = "Hi {$name}, your withdrawal of {$amount} (ref {$reference}) was declined. The held amount has been returned to your NovaCoin wallet.";

        if (filled($walletWithdrawal->decline_reason)) {
            $message .= ' Reason: '.$walletWithdrawal->decline_reason;
        }

        return [
            'title' => "{$name}, your withdrawal was declined",
            'message' => $message,
        ];
    }

    private function firstName(User $user): string
    {
        $name = trim((string) ($user->first_name ?? ''));

        return $name !== '' ? $name : 'there';
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
