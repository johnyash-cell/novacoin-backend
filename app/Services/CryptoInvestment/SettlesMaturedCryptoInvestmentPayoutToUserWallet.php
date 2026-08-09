<?php

namespace App\Services\CryptoInvestment;

use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\CryptoInvestment;
use App\Models\CryptoInvestmentDailyValuation;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Services\Mail\ComposesMemberLifecycleEmailCopy;
use App\Services\Mail\SendsMemberTransactionalEmail;
use App\Services\Wallet\ResolvesUserWallet;
use Illuminate\Support\Facades\DB;

class SettlesMaturedCryptoInvestmentPayoutToUserWallet
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
        private MarksCryptoInvestmentToMarketForDay $marksCryptoInvestmentToMarketForDay,
        private ComposesMemberLifecycleEmailCopy $composesMemberLifecycleEmailCopy,
        private SendsMemberTransactionalEmail $sendsMemberTransactionalEmail,
    ) {}

    /**
     * Credit current escrow to the spendable wallet once the term is complete.
     *
     * @return bool True when a payout was written
     */
    public function settleIfDue(CryptoInvestment $cryptoInvestment): bool
    {
        if ($cryptoInvestment->payout_completed_at !== null) {
            return false;
        }

        if ($cryptoInvestment->matures_at === null || $cryptoInvestment->matures_at->isFuture()) {
            return false;
        }

        $this->marksCryptoInvestmentToMarketForDay->mark($cryptoInvestment);
        $cryptoInvestment->refresh();

        $termDays = (int) $cryptoInvestment->term_days;
        $loggedDays = CryptoInvestmentDailyValuation::query()
            ->where('crypto_investment_id', $cryptoInvestment->id)
            ->count();

        if ($termDays > 0 && $loggedDays < $termDays) {
            return false;
        }

        $settlement = DB::transaction(function () use ($cryptoInvestment): ?array {
            /** @var CryptoInvestment $lockedInvestment */
            $lockedInvestment = CryptoInvestment::query()
                ->whereKey($cryptoInvestment->id)
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

            $payoutAmountUsd = round((float) $lockedInvestment->current_escrow_usd, 2);

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
                'entry_type' => WalletLedgerEntryType::CryptoInvestmentPayoutCredit->value,
                'amount' => $payoutAmountUsd,
                'balance_after' => $balanceAfter,
                'crypto_investment_id' => $lockedInvestment->id,
                'description' => 'Crypto investment payout for '.$lockedInvestment->asset_label,
                'created_at' => now(),
            ]);

            $lockedInvestment->forceFill([
                'status' => InvestmentStatus::Ended->value,
                'ended_at' => $lockedInvestment->ended_at ?? now(),
                'payout_completed_at' => now(),
            ])->save();

            $cryptoInvestment->refresh();

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
            $this->composesMemberLifecycleEmailCopy->cryptoInvestmentMatured(
                $cryptoInvestment,
                $settlement['payout_amount_usd'],
            ),
        );

        return true;
    }
}
