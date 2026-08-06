<?php

namespace App\Services\Admin;

use App\Enums\InvestmentStatus;
use App\Enums\WalletDepositStatus;
use App\Enums\WalletWithdrawalStatus;
use App\Models\Investment;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\ResolvesUserWallet;

class BuildsAdminUserProfileSummary
{
    public function __construct(
        private readonly ResolvesUserWallet $resolvesUserWallet,
    ) {}

    /**
     * @return array{
     *     wallet_available_balance: float,
     *     wallet_currency_code: string,
     *     total_deposits_usd: float,
     *     total_withdrawals_usd: float,
     *     active_investments_count: int
     * }
     */
    public function build(User $user): array
    {
        $wallet = $this->resolvesUserWallet->resolveForUser($user);

        $totalDepositsUsd = (float) WalletDeposit::query()
            ->where('user_id', $user->id)
            ->where('status', WalletDepositStatus::Approved->value)
            ->sum('usd_amount');

        $totalWithdrawalsUsd = (float) WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->where('status', WalletWithdrawalStatus::Approved->value)
            ->sum('usd_amount');

        Investment::endAllDue();

        $activeInvestmentsCount = Investment::query()
            ->forUser($user->id)
            ->withStoredStatus(InvestmentStatus::Active->value)
            ->count();

        return [
            'wallet_available_balance' => (float) $wallet->available_balance,
            'wallet_currency_code' => (string) $wallet->currency_code,
            'total_deposits_usd' => $totalDepositsUsd,
            'total_withdrawals_usd' => $totalWithdrawalsUsd,
            'active_investments_count' => $activeInvestmentsCount,
        ];
    }
}
