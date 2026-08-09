<?php

namespace App\Services\CryptoInvestment;

use App\Models\CryptoInvestment;
use App\Models\CryptoInvestmentDailyValuation;
use App\Services\Wallet\FetchesCoinGeckoUsdAssetPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarksCryptoInvestmentToMarketForDay
{
    public function __construct(
        private FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
    ) {}

    /**
     * Persist missing daily mark-to-market valuations into escrow. Does not touch spendable wallet.
     *
     * First valuation is the calendar day after started_at.
     *
     * @return int Number of new valuation rows created
     */
    public function mark(CryptoInvestment $cryptoInvestment, ?Carbon $asOfDate = null): int
    {
        if ($cryptoInvestment->payout_completed_at !== null) {
            return 0;
        }

        $termDays = (int) $cryptoInvestment->term_days;

        if ($termDays < 1 || $cryptoInvestment->started_at === null) {
            return 0;
        }

        $asOf = ($asOfDate ?? now())->copy()->startOfDay();
        $startDate = $cryptoInvestment->started_at->copy()->startOfDay();
        $firstValuationDate = $startDate->copy()->addDay();
        $lastValuationDate = $startDate->copy()->addDays($termDays);

        return (int) DB::transaction(function () use (
            $cryptoInvestment,
            $asOf,
            $startDate,
            $firstValuationDate,
            $lastValuationDate,
            $termDays,
        ): int {
            /** @var CryptoInvestment $lockedInvestment */
            $lockedInvestment = CryptoInvestment::query()
                ->whereKey($cryptoInvestment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvestment->payout_completed_at !== null) {
                return 0;
            }

            $existingDates = CryptoInvestmentDailyValuation::query()
                ->where('crypto_investment_id', $lockedInvestment->id)
                ->pluck('valuation_date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all();

            $existingDateLookup = array_fill_keys($existingDates, true);
            $createdCount = 0;
            $currentEscrowUsd = round((float) $lockedInvestment->current_escrow_usd, 2);
            $lastPriceUsd = $lockedInvestment->last_price_usd !== null
                ? (float) $lockedInvestment->last_price_usd
                : (float) $lockedInvestment->entry_price_usd;

            for ($dayIndex = 1; $dayIndex <= $termDays; $dayIndex++) {
                $valuationDate = $startDate->copy()->addDays($dayIndex);

                if ($valuationDate->lessThan($firstValuationDate)) {
                    continue;
                }

                if ($valuationDate->greaterThan($asOf) || $valuationDate->greaterThan($lastValuationDate)) {
                    break;
                }

                $valuationDateString = $valuationDate->toDateString();

                if (isset($existingDateLookup[$valuationDateString])) {
                    continue;
                }

                $priceUsd = $this->fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit(
                    (string) $lockedInvestment->coingecko_asset_id,
                );

                $markValueUsd = round((float) $lockedInvestment->units * $priceUsd, 2);
                $escrowBeforeUsd = $currentEscrowUsd;
                $wasClampedByMaxLoss = false;
                $escrowAfterUsd = $markValueUsd;

                if ($lockedInvestment->max_loss_enabled && $lockedInvestment->max_loss_floor_usd !== null) {
                    $floorUsd = round((float) $lockedInvestment->max_loss_floor_usd, 2);

                    if ($escrowAfterUsd < $floorUsd) {
                        $escrowAfterUsd = $floorUsd;
                        $wasClampedByMaxLoss = true;
                    }
                }

                $escrowAfterUsd = max(0.0, $escrowAfterUsd);
                $deltaUsd = round($escrowAfterUsd - $escrowBeforeUsd, 2);

                CryptoInvestmentDailyValuation::query()->create([
                    'crypto_investment_id' => $lockedInvestment->id,
                    'valuation_date' => $valuationDateString,
                    'price_usd' => $priceUsd,
                    'escrow_before_usd' => $escrowBeforeUsd,
                    'escrow_after_usd' => $escrowAfterUsd,
                    'delta_usd' => $deltaUsd,
                    'was_clamped_by_max_loss' => $wasClampedByMaxLoss,
                    'created_at' => now(),
                ]);

                $currentEscrowUsd = $escrowAfterUsd;
                $lastPriceUsd = $priceUsd;
                $existingDateLookup[$valuationDateString] = true;
                $createdCount++;
            }

            if ($createdCount > 0) {
                $lockedInvestment->forceFill([
                    'current_escrow_usd' => $currentEscrowUsd,
                    'last_price_usd' => $lastPriceUsd,
                ])->save();
            }

            $cryptoInvestment->refresh();

            return $createdCount;
        });
    }
}
