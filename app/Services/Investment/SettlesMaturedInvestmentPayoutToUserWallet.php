<?php

namespace App\Services\Investment;

use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Investment;
use App\Models\InvestmentDailyEarningLog;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Services\Mail\ComposesMemberLifecycleEmailCopy;
use App\Services\Mail\SendsMemberTransactionalEmail;
use App\Services\Wallet\ResolvesUserWallet;
use Illuminate\Support\Facades\DB;

class SettlesMaturedInvestmentPayoutToUserWallet
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
        private AccruesFlatDailyReturnForInvestment $accruesFlatDailyReturnForInvestment,
        private ComposesMemberLifecycleEmailCopy $composesMemberLifecycleEmailCopy,
        private SendsMemberTransactionalEmail $sendsMemberTransactionalEmail,
    ) {}

    /**
     * Credit principal + accrued return to the spendable wallet once the term is complete.
     *
     * @return bool True when a payout was written
     */
    public function settleIfDue(Investment $investment): bool
    {
        if ($investment->payout_completed_at !== null) {
            return false;
        }

        if ($investment->matures_at === null || $investment->matures_at->isFuture()) {
            return false;
        }

        // Ensure every term day is logged before paying the main wallet.
        $this->accruesFlatDailyReturnForInvestment->accrue($investment);
        $investment->refresh();

        $termDays = (int) $investment->term_days;
        $loggedDays = InvestmentDailyEarningLog::query()
            ->where('investment_id', $investment->id)
            ->count();

        if ($termDays > 0 && $loggedDays < $termDays) {
            return false;
        }

        $settlement = DB::transaction(function () use ($investment): ?array {
            /** @var Investment $lockedInvestment */
            $lockedInvestment = Investment::query()
                ->whereKey($investment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvestment->payout_completed_at !== null) {
                return null;
            }

            if ($lockedInvestment->matures_at === null || $lockedInvestment->matures_at->isFuture()) {
                return null;
            }

            $user = User::query()->find($lockedInvestment->user_id);

            if ($user === null) {
                return null;
            }

            $payoutAmountUsd = round(
                (float) $lockedInvestment->amount_usd + (float) $lockedInvestment->accrued_return_usd,
                2,
            );

            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = round((float) $lockedWallet->available_balance + $payoutAmountUsd, 2);

            $lockedWallet->forceFill([
                'available_balance' => $balanceAfter,
            ])->save();

            WalletLedgerEntry::query()->create([
                'user_wallet_id' => $lockedWallet->id,
                'entry_type' => WalletLedgerEntryType::InvestmentPayoutCredit->value,
                'amount' => $payoutAmountUsd,
                'balance_after' => $balanceAfter,
                'investment_id' => $lockedInvestment->id,
                'description' => 'Investment payout for '.$lockedInvestment->package_name,
                'created_at' => now(),
            ]);

            $lockedInvestment->forceFill([
                'status' => InvestmentStatus::Ended->value,
                'ended_at' => $lockedInvestment->ended_at ?? now(),
                'payout_completed_at' => now(),
            ])->save();

            $investment->refresh();

            return [
                'user' => $user,
                'payout_amount_usd' => $payoutAmountUsd,
            ];
        });

        if ($settlement === null) {
            return false;
        }

        $this->sendsMemberTransactionalEmail->sendCopy(
            $settlement['user'],
            $this->composesMemberLifecycleEmailCopy->fixedInvestmentMatured(
                $settlement['user'],
                $investment,
                $settlement['payout_amount_usd'],
            ),
        );

        return true;
    }
}
