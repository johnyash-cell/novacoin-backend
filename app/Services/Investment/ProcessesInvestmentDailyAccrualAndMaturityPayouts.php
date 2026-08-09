<?php

namespace App\Services\Investment;

use App\Models\Investment;
use Illuminate\Database\Eloquent\Builder;

class ProcessesInvestmentDailyAccrualAndMaturityPayouts
{
    public function __construct(
        private AccruesFlatDailyReturnForInvestment $accruesFlatDailyReturnForInvestment,
        private SettlesMaturedInvestmentPayoutToUserWallet $settlesMaturedInvestmentPayoutToUserWallet,
    ) {}

    /**
     * @return array{daily_logs_created: int, payouts_completed: int}
     */
    public function processAll(): array
    {
        return $this->processQuery(
            Investment::query()->whereNull('payout_completed_at'),
        );
    }

    /**
     * @return array{daily_logs_created: int, payouts_completed: int}
     */
    public function processForUser(int $userId): array
    {
        return $this->processQuery(
            Investment::query()
                ->where('user_id', $userId)
                ->whereNull('payout_completed_at'),
        );
    }

    /**
     * @return array{daily_logs_created: int, payouts_completed: int}
     */
    public function processInvestment(Investment $investment): array
    {
        $dailyLogsCreated = $this->accruesFlatDailyReturnForInvestment->accrue($investment);
        $payoutCompleted = $this->settlesMaturedInvestmentPayoutToUserWallet->settleIfDue($investment);

        return [
            'daily_logs_created' => $dailyLogsCreated,
            'payouts_completed' => $payoutCompleted ? 1 : 0,
        ];
    }

    /**
     * @param  Builder<Investment>  $query
     * @return array{daily_logs_created: int, payouts_completed: int}
     */
    private function processQuery(Builder $query): array
    {
        $dailyLogsCreated = 0;
        $payoutsCompleted = 0;

        $query
            ->orderBy('id')
            ->each(function (Investment $investment) use (&$dailyLogsCreated, &$payoutsCompleted): void {
                $result = $this->processInvestment($investment);
                $dailyLogsCreated += $result['daily_logs_created'];
                $payoutsCompleted += $result['payouts_completed'];
            });

        return [
            'daily_logs_created' => $dailyLogsCreated,
            'payouts_completed' => $payoutsCompleted,
        ];
    }
}
