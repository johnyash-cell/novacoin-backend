<?php

namespace App\Services\Investment;

use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Investment;
use App\Models\InvestmentPackage;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\ResolvesUserWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebitsUserWalletForPackageInvestment
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
    ) {}

    public function invest(User $user, InvestmentPackage $investmentPackage, float $amountUsd): Investment
    {
        return DB::transaction(function () use ($user, $investmentPackage, $amountUsd): Investment {
            /** @var InvestmentPackage $lockedPackage */
            $lockedPackage = InvestmentPackage::query()
                ->whereKey($investmentPackage->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPackage->expireIfDue();
            $lockedPackage->refresh();

            if (! $lockedPackage->isJoinable()) {
                throw ValidationException::withMessages([
                    'investment_package_id' => ['This investment package is not available for new investments.'],
                ]);
            }

            if ($amountUsd < (float) $lockedPackage->minimum_amount_usd) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['Investment amount must be at least '.$lockedPackage->minimum_amount_usd.' USD.'],
                ]);
            }

            if ($lockedPackage->maximum_amount_usd !== null && $amountUsd > (float) $lockedPackage->maximum_amount_usd) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['Investment amount must not exceed '.$lockedPackage->maximum_amount_usd.' USD.'],
                ]);
            }

            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $lockedWallet->available_balance < $amountUsd) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['Insufficient wallet balance for this investment amount.'],
                ]);
            }

            $expectedReturnAmountUsd = round($amountUsd * ((float) $lockedPackage->expected_return_percent / 100), 2);
            $expectedPayoutAmountUsd = round($amountUsd + $expectedReturnAmountUsd, 2);
            $startedAt = now();
            $maturesAt = $startedAt->copy()->addDays((int) $lockedPackage->term_days);

            $balanceAfter = round((float) $lockedWallet->available_balance - $amountUsd, 2);

            $lockedWallet->forceFill([
                'available_balance' => $balanceAfter,
            ])->save();

            $investment = Investment::query()->create([
                'user_id' => $user->id,
                'investment_package_id' => $lockedPackage->id,
                'package_name' => $lockedPackage->name,
                'amount_usd' => $amountUsd,
                'expected_return_percent' => $lockedPackage->expected_return_percent,
                'term_days' => $lockedPackage->term_days,
                'expected_return_amount_usd' => $expectedReturnAmountUsd,
                'expected_payout_amount_usd' => $expectedPayoutAmountUsd,
                'accrued_return_usd' => 0,
                'status' => InvestmentStatus::Active->value,
                'started_at' => $startedAt,
                'matures_at' => $maturesAt,
                'payout_completed_at' => null,
            ]);

            WalletLedgerEntry::query()->create([
                'user_wallet_id' => $lockedWallet->id,
                'entry_type' => WalletLedgerEntryType::InvestmentDebit->value,
                'amount' => -abs($amountUsd),
                'balance_after' => $balanceAfter,
                'investment_id' => $investment->id,
                'description' => 'Investment placed in '.$lockedPackage->name,
                'created_at' => now(),
            ]);

            $lockedPackage->forceFill([
                'joined_count' => $lockedPackage->joined_count + 1,
            ])->save();

            return $investment->fresh(['investmentPackage']) ?? $investment;
        });
    }
}
