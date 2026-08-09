<?php

namespace App\Services\Referral;

use App\Enums\ReferralRewardPayoutMode;
use App\Enums\WalletLedgerEntryType;
use App\Models\Admin;
use App\Models\ReferralRewardPayout;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletDeposit;
use App\Models\WalletLedgerEntry;
use App\Services\Mail\ComposesMemberLifecycleEmailCopy;
use App\Services\Mail\SendsMemberTransactionalEmail;
use App\Services\Wallet\ResolvesUserWallet;

class PaysReferrerRewardForApprovedDeposit
{
    public function __construct(
        private ResolvesReferralProgramSettings $resolvesReferralProgramSettings,
        private ResolvesUserWallet $resolvesUserWallet,
        private ComposesMemberLifecycleEmailCopy $composesMemberLifecycleEmailCopy,
        private SendsMemberTransactionalEmail $sendsMemberTransactionalEmail,
    ) {}

    /**
     * Must run inside the deposit-approval DB transaction after the depositor is credited.
     */
    public function payIfEligible(WalletDeposit $walletDeposit, Admin $admin): void
    {
        $referredUser = User::query()->find($walletDeposit->user_id);

        if ($referredUser === null || $referredUser->referred_by_user_id === null) {
            return;
        }

        // Never pay the same approved deposit twice.
        if (ReferralRewardPayout::query()->where('wallet_deposit_id', $walletDeposit->id)->exists()) {
            return;
        }

        $payoutMode = $this->resolvesReferralProgramSettings->payoutMode();

        if (
            $payoutMode === ReferralRewardPayoutMode::FirstApprovedDepositOnly
            && ReferralRewardPayout::query()
                ->where('referred_user_id', $referredUser->id)
                ->exists()
        ) {
            return;
        }

        $referrer = User::query()->find($referredUser->referred_by_user_id);

        if ($referrer === null) {
            return;
        }

        $rewardAmount = $this->resolvesReferralProgramSettings->rewardAmountUsd();

        if ($rewardAmount <= 0) {
            return;
        }

        $referrerWallet = $this->resolvesUserWallet->resolveForUser($referrer);
        /** @var UserWallet $lockedReferrerWallet */
        $lockedReferrerWallet = UserWallet::query()
            ->whereKey($referrerWallet->id)
            ->lockForUpdate()
            ->firstOrFail();

        $balanceAfter = round((float) $lockedReferrerWallet->available_balance + $rewardAmount, 2);

        $lockedReferrerWallet->forceFill([
            'available_balance' => $balanceAfter,
        ])->save();

        $payout = ReferralRewardPayout::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referredUser->id,
            'wallet_deposit_id' => $walletDeposit->id,
            'amount' => $rewardAmount,
            'created_at' => now(),
        ]);

        WalletLedgerEntry::query()->create([
            'user_wallet_id' => $lockedReferrerWallet->id,
            'entry_type' => WalletLedgerEntryType::ReferralCredit->value,
            'amount' => $rewardAmount,
            'balance_after' => $balanceAfter,
            'wallet_deposit_id' => $walletDeposit->id,
            'referral_reward_payout_id' => $payout->id,
            'description' => 'Referral reward for approved deposit',
            'created_by_admin_id' => $admin->id,
            'created_at' => now(),
        ]);

        $this->sendsMemberTransactionalEmail->sendCopy(
            $referrer,
            $this->composesMemberLifecycleEmailCopy->referralRewardPaid($payout),
        );
    }
}
