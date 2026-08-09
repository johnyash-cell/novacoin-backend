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
        $appName = (string) config('app.name');

        return [
            'subject' => "Welcome to {$appName}, {$name}",
            'heading' => "Welcome aboard, {$name}",
            'body' => $this->lines([
                "Hi {$name},",
                "Thanks for joining {$appName}. Your member account ({$user->email}) is ready.",
                'Next steps: fund your wallet from Fund Account, then explore investment plans from your dashboard.',
                'If you did not create this account, contact support right away.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function depositSubmitted(User $user, WalletDeposit $deposit): array
    {
        $name = $this->firstName($user);
        $amount = $this->money((float) $deposit->usd_amount);
        $crypto = $this->cryptoAmount((float) $deposit->crypto_amount_expected);
        $asset = (string) ($deposit->asset_symbol ?: 'crypto');
        $network = (string) ($deposit->network_name ?: 'network');
        $address = (string) ($deposit->wallet_address ?: 'the platform address');
        $reference = (string) ($deposit->reference_number ?: '#'.$deposit->id);

        return [
            'subject' => "{$name}, your \${$amount} deposit is under review",
            'heading' => "Hi {$name}, we got your deposit",
            'body' => $this->lines([
                "Hi {$name},",
                "We received your Fund Account deposit of \${$amount} USD (ref {$reference}).",
                'Payment details:',
                "- Asset: {$asset}",
                "- Network: {$network}",
                "- Expected crypto amount: {$crypto} {$asset}",
                "- Platform address: {$address}",
                'Your proof is with our team for review. You will get another email when this deposit is approved or declined.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function withdrawalSubmitted(User $user, WalletWithdrawal $withdrawal): array
    {
        $name = $this->firstName($user);
        $amount = $this->money((float) $withdrawal->usd_amount);
        $crypto = $this->cryptoAmount((float) $withdrawal->crypto_amount_expected);
        $asset = (string) ($withdrawal->asset_symbol ?: 'crypto');
        $network = (string) ($withdrawal->network_name ?: 'network');
        $destination = (string) ($withdrawal->destination_wallet_address ?: 'your destination wallet');
        $reference = (string) ($withdrawal->reference_number ?: '#'.$withdrawal->id);

        return [
            'subject' => "{$name}, your \${$amount} withdrawal was submitted",
            'heading' => "Hi {$name}, withdrawal request received",
            'body' => $this->lines([
                "Hi {$name},",
                "Your withdrawal of \${$amount} USD (ref {$reference}) is submitted and under review.",
                'Payout details:',
                "- Asset: {$asset}",
                "- Network: {$network}",
                "- Expected send amount: {$crypto} {$asset}",
                "- Destination: {$destination}",
                "\${$amount} USD is held from your available balance until this request is approved or declined.",
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function fixedInvestmentPlaced(User $user, Investment $investment): array
    {
        $name = $this->firstName($user);
        $amount = $this->money((float) $investment->amount_usd);
        $package = (string) $investment->package_name;
        $returnPercent = $this->money((float) $investment->expected_return_percent);
        $expectedReturn = $this->money((float) $investment->expected_return_amount_usd);
        $expectedPayout = $this->money((float) $investment->expected_payout_amount_usd);
        $termDays = (int) $investment->term_days;
        $matures = $investment->matures_at?->toDayDateTimeString() ?? 'the maturity date';

        return [
            'subject' => "{$name}, your {$package} investment is confirmed",
            'heading' => "Hi {$name}, investment confirmed",
            'body' => $this->lines([
                "Hi {$name},",
                "You invested \${$amount} USD in {$package}.",
                'Plan details:',
                "- Term: {$termDays} days",
                "- Expected return: {$returnPercent}% (\${$expectedReturn} USD)",
                "- Expected payout at maturity: \${$expectedPayout} USD",
                "- Matures: {$matures}",
                'Returns accrue daily into escrow and credit your spendable wallet at maturity.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function fixedInvestmentMatured(User $user, Investment $investment, float $payoutAmountUsd): array
    {
        $name = $this->firstName($user);
        $payout = $this->money($payoutAmountUsd);
        $package = (string) $investment->package_name;
        $principal = $this->money((float) $investment->amount_usd);
        $accrued = $this->money((float) $investment->accrued_return_usd);

        return [
            'subject' => "{$name}, \${$payout} from {$package} was credited",
            'heading' => "Hi {$name}, your investment matured",
            'body' => $this->lines([
                "Hi {$name},",
                "Your {$package} investment has matured.",
                'Payout breakdown:',
                "- Principal: \${$principal} USD",
                "- Accrued return: \${$accrued} USD",
                "- Total credited: \${$payout} USD",
                "That total is now in your spendable {$this->appName()} wallet.",
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function cryptoInvestmentPlaced(User $user, CryptoInvestment $investment): array
    {
        $name = $this->firstName($user);
        $amount = $this->money((float) $investment->amount_usd);
        $committed = $this->money((float) $investment->committed_usd);
        $fee = $this->money((float) $investment->fee_usd);
        $entry = $this->money((float) $investment->entry_price_usd);
        $units = rtrim(rtrim(number_format((float) $investment->units, 8, '.', ''), '0'), '.');
        $assetLabel = (string) $investment->asset_label;
        $symbol = (string) $investment->asset_symbol;
        $termDays = (int) $investment->term_days;
        $matures = $investment->matures_at?->toDayDateTimeString() ?? 'the maturity date';

        return [
            'subject' => "{$name}, your {$symbol} crypto investment is confirmed",
            'heading' => "Hi {$name}, crypto investment confirmed",
            'body' => $this->lines([
                "Hi {$name},",
                "You placed a crypto investment in {$assetLabel} ({$symbol}).",
                'Position details:',
                "- Invest amount: \${$amount} USD",
                "- Committed exposure: \${$committed} USD",
                "- Fee: \${$fee} USD",
                "- Entry price: \${$entry} USD",
                "- Units: {$units} {$symbol}",
                "- Term: {$termDays} days (ends {$matures})",
                'Your wallet is credited at maturity.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function cryptoInvestmentMatured(User $user, CryptoInvestment $investment, float $payoutAmountUsd): array
    {
        $name = $this->firstName($user);
        $payout = $this->money($payoutAmountUsd);
        $committed = $this->money((float) $investment->committed_usd);
        $assetLabel = (string) $investment->asset_label;
        $symbol = (string) $investment->asset_symbol;
        $pnl = round($payoutAmountUsd - (float) $investment->committed_usd, 2);
        $pnlLabel = ($pnl >= 0 ? '+' : '-').'$'.$this->money(abs($pnl));

        return [
            'subject' => "{$name}, \${$payout} from your {$symbol} investment was credited",
            'heading' => "Hi {$name}, crypto investment matured",
            'body' => $this->lines([
                "Hi {$name},",
                "Your {$assetLabel} ({$symbol}) investment has matured.",
                'Settlement details:',
                "- Committed exposure: \${$committed} USD",
                "- Final escrow payout: \${$payout} USD",
                "- Change vs committed: {$pnlLabel} USD",
                "The payout is now in your spendable {$this->appName()} wallet.",
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function referralRewardPaid(User $referrer, ReferralRewardPayout $payout, ?User $referredUser = null): array
    {
        $name = $this->firstName($referrer);
        $amount = $this->money((float) $payout->amount);
        $referredLabel = $referredUser !== null
            ? trim($this->fullName($referredUser).' ('.$referredUser->email.')')
            : 'your referred member';

        return [
            'subject' => "{$name}, you earned a \${$amount} referral reward",
            'heading' => "Hi {$name}, referral reward credited",
            'body' => $this->lines([
                "Hi {$name},",
                "Great news — a referral reward of \${$amount} USD was credited to your spendable wallet.",
                "This reward is for {$referredLabel}'s approved deposit.",
                'Keep sharing your referral link to earn more.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountBanned(User $user, ?string $reason = null): array
    {
        $name = $this->firstName($user);
        $reasonLine = filled($reason)
            ? "Reason noted by our team: {$reason}"
            : 'No additional reason was provided.';

        return [
            'subject' => "{$name}, your {$this->appName()} account has been banned",
            'heading' => "Hi {$name}, account banned",
            'body' => $this->lines([
                "Hi {$name},",
                "Your {$this->appName()} account ({$user->email}) has been banned. You can no longer sign in.",
                $reasonLine,
                'If you believe this is a mistake, contact support.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountSuspended(User $user, ?string $suspendedUntil, ?string $reason = null): array
    {
        $name = $this->firstName($user);
        $untilLine = filled($suspendedUntil)
            ? "Your suspension lasts until {$suspendedUntil}."
            : 'Your suspension end time is on your account profile.';
        $reasonLine = filled($reason)
            ? "Reason noted by our team: {$reason}"
            : 'No additional reason was provided.';

        return [
            'subject' => "{$name}, your {$this->appName()} account has been suspended",
            'heading' => "Hi {$name}, account suspended",
            'body' => $this->lines([
                "Hi {$name},",
                "Your {$this->appName()} account ({$user->email}) has been suspended. Access is temporarily limited.",
                $untilLine,
                $reasonLine,
                'Contact support if you need help.',
            ]),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountUnsuspended(User $user, ?string $reason = null): array
    {
        $name = $this->firstName($user);
        $reasonLine = filled($reason)
            ? "Note from our team: {$reason}"
            : null;

        return [
            'subject' => "{$name}, your {$this->appName()} suspension was lifted",
            'heading' => "Hi {$name}, you can sign in again",
            'body' => $this->lines(array_values(array_filter([
                "Hi {$name},",
                "Good news — the suspension on your {$this->appName()} account ({$user->email}) has been lifted.",
                $reasonLine,
                'You can sign in and use the platform again.',
            ]))),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function accountReactivated(User $user, ?string $reason = null): array
    {
        $name = $this->firstName($user);
        $reasonLine = filled($reason)
            ? "Note from our team: {$reason}"
            : null;

        return [
            'subject' => "{$name}, your {$this->appName()} account was reactivated",
            'heading' => "Hi {$name}, account reactivated",
            'body' => $this->lines(array_values(array_filter([
                "Hi {$name},",
                "Your {$this->appName()} account ({$user->email}) has been reactivated.",
                $reasonLine,
                'You can sign in and use the platform again.',
            ]))),
        ];
    }

    /**
     * @return array{subject: string, heading: string, body: string}
     */
    public function promotedToAdmin(User $user): array
    {
        $name = $this->firstName($user);
        $appName = (string) config('app.name');

        return [
            'subject' => "{$name}, you were granted {$appName} admin access",
            'heading' => "Hi {$name}, admin access granted",
            'body' => $this->lines([
                "Hi {$name},",
                "An administrator granted admin access to your {$appName} account ({$user->email}).",
                'Use the admin password that was set for you when you were promoted. Keep that password secure and do not share it.',
                'You can now sign in to the admin panel with this email.',
            ]),
        ];
    }

    private function appName(): string
    {
        return (string) config('app.name');
    }

    private function firstName(User $user): string
    {
        $name = trim((string) ($user->first_name ?? ''));

        return $name !== '' ? $name : 'there';
    }

    private function fullName(User $user): string
    {
        $full = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));

        return $full !== '' ? $full : $this->firstName($user);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private function cryptoAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @param  list<string>  $lines
     */
    private function lines(array $lines): string
    {
        return implode("\n\n", $lines);
    }
}
