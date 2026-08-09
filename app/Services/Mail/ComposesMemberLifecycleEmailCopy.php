<?php

namespace App\Services\Mail;

use App\Models\CryptoInvestment;
use App\Models\Investment;
use App\Models\ReferralRewardPayout;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletWithdrawal;

class ComposesMemberLifecycleEmailCopy
{
    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function welcome(User $user): array
    {
        $name = $this->firstName($user);

        return [
            'subject' => 'Welcome to '.config('app.name'),
            'heading' => 'Welcome, '.$name,
            'body' => 'Your account is ready. Fund your wallet, explore investment plans, and track everything from your dashboard.',
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function depositSubmitted(WalletDeposit $deposit): array
    {
        $amount = $this->money((float) $deposit->usd_amount);

        return [
            'subject' => 'Deposit received — under review',
            'heading' => 'Deposit submitted',
            'body' => "We received your deposit of {$amount} USD (ref {$deposit->reference_number}). It is under review. You will get another email when it is approved or declined.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function withdrawalSubmitted(WalletWithdrawal $withdrawal): array
    {
        $amount = $this->money((float) $withdrawal->usd_amount);

        return [
            'subject' => 'Withdrawal request submitted',
            'heading' => 'Withdrawal submitted',
            'body' => "Your withdrawal of {$amount} USD (ref {$withdrawal->reference_number}) has been submitted. That amount is held from your available balance while we review it.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function fixedInvestmentPlaced(Investment $investment): array
    {
        $amount = $this->money((float) $investment->amount_usd);
        $matures = $investment->matures_at?->toDayDateTimeString() ?? 'the maturity date';

        return [
            'subject' => 'Investment placed — '.$investment->package_name,
            'heading' => 'Investment confirmed',
            'body' => "You invested {$amount} USD in {$investment->package_name}. Your term runs until {$matures}. Returns accrue daily into escrow and pay out to your wallet at maturity.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function fixedInvestmentMatured(Investment $investment, float $payoutAmountUsd): array
    {
        $payout = $this->money($payoutAmountUsd);

        return [
            'subject' => 'Investment payout credited — '.$investment->package_name,
            'heading' => 'Investment matured',
            'body' => "Your investment in {$investment->package_name} has matured. {$payout} USD has been credited to your spendable wallet.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function cryptoInvestmentPlaced(CryptoInvestment $investment): array
    {
        $amount = $this->money((float) $investment->amount_usd);
        $committed = $this->money((float) $investment->committed_usd);
        $matures = $investment->matures_at?->toDayDateTimeString() ?? 'the maturity date';

        return [
            'subject' => 'Crypto investment placed — '.$investment->asset_symbol,
            'heading' => 'Crypto investment confirmed',
            'body' => "You invested {$amount} USD in {$investment->asset_label} ({$investment->asset_symbol}). Committed exposure: {$committed} USD. Entry price: {$investment->entry_price_usd} USD. Term ends {$matures}. Escrow follows the live market; payout credits your wallet at maturity.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function cryptoInvestmentMatured(CryptoInvestment $investment, float $payoutAmountUsd): array
    {
        $payout = $this->money($payoutAmountUsd);

        return [
            'subject' => 'Crypto investment payout credited — '.$investment->asset_symbol,
            'heading' => 'Crypto investment matured',
            'body' => "Your {$investment->asset_label} ({$investment->asset_symbol}) investment has matured. {$payout} USD (final escrow) has been credited to your spendable wallet.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function referralRewardPaid(ReferralRewardPayout $payout): array
    {
        $amount = $this->money((float) $payout->amount);

        return [
            'subject' => 'Referral reward credited',
            'heading' => 'Referral reward received',
            'body' => "Great news — a referral reward of {$amount} USD has been credited to your spendable wallet.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountBanned(): array
    {
        return [
            'subject' => 'Your account has been banned',
            'heading' => 'Account banned',
            'body' => 'Your account has been banned and you can no longer sign in. If you believe this is a mistake, contact support.',
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountSuspended(?string $suspendedUntil): array
    {
        $until = filled($suspendedUntil)
            ? " Your suspension lasts until {$suspendedUntil}."
            : '';

        return [
            'subject' => 'Your account has been suspended',
            'heading' => 'Account suspended',
            'body' => "Your account has been suspended and access is temporarily limited.{$until} Contact support if you need help.",
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountUnsuspended(): array
    {
        return [
            'subject' => 'Your account suspension was lifted',
            'heading' => 'Account unsuspended',
            'body' => 'Your account suspension has been lifted. You can sign in and use the platform again.',
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountReactivated(): array
    {
        return [
            'subject' => 'Your account was reactivated',
            'heading' => 'Account reactivated',
            'body' => 'Your account has been reactivated. You can sign in and use the platform again.',
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function promotedToAdmin(User $user): array
    {
        $name = $this->firstName($user);

        return [
            'subject' => 'You were granted admin access',
            'heading' => 'Admin access granted',
            'body' => "Hi {$name}, an administrator granted you admin access to ".config('app.name').'. Use your admin login credentials to access the admin panel. Keep your password secure.',
        ];
    }

    private function firstName(User $user): string
    {
        $name = trim((string) ($user->first_name ?? ''));

        return $name !== '' ? $name : 'there';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
