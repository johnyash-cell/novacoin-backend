<?php

namespace App\Services\CryptoInvestment;

use App\Models\CryptoInvestment;
use Illuminate\Database\Eloquent\Builder;

class ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts
{
    public function __construct(
        private MarksCryptoInvestmentToMarketForDay $marksCryptoInvestmentToMarketForDay,
        private SettlesMaturedCryptoInvestmentPayoutToUserWallet $settlesMaturedCryptoInvestmentPayoutToUserWallet,
    ) {}

    /**
     * @return array{valuations_created: int, payouts_completed: int}
     */
    public function processAll(): array
    {
        return $this->processQuery(
            CryptoInvestment::query()->whereNull('payout_completed_at'),
        );
    }

    /**
     * @return array{valuations_created: int, payouts_completed: int}
     */
    public function processForUser(int $userId): array
    {
        return $this->processQuery(
            CryptoInvestment::query()
                ->where('user_id', $userId)
                ->whereNull('payout_completed_at'),
        );
    }

    /**
     * @return array{valuations_created: int, payouts_completed: int}
     */
    public function processInvestment(CryptoInvestment $cryptoInvestment): array
    {
        $valuationsCreated = $this->marksCryptoInvestmentToMarketForDay->mark($cryptoInvestment);
        $payoutCompleted = $this->settlesMaturedCryptoInvestmentPayoutToUserWallet->settleIfDue($cryptoInvestment);

        return [
            'valuations_created' => $valuationsCreated,
            'payouts_completed' => $payoutCompleted ? 1 : 0,
        ];
    }

    /**
     * @param  Builder<CryptoInvestment>  $query
     * @return array{valuations_created: int, payouts_completed: int}
     */
    private function processQuery(Builder $query): array
    {
        $valuationsCreated = 0;
        $payoutsCompleted = 0;

        $query
            ->orderBy('id')
            ->each(function (CryptoInvestment $cryptoInvestment) use (&$valuationsCreated, &$payoutsCompleted): void {
                $result = $this->processInvestment($cryptoInvestment);
                $valuationsCreated += $result['valuations_created'];
                $payoutsCompleted += $result['payouts_completed'];
            });

        return [
            'valuations_created' => $valuationsCreated,
            'payouts_completed' => $payoutsCompleted,
        ];
    }
}
