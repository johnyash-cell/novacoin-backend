<?php

namespace App\Services\Investment;

use App\Models\Investment;
use App\Models\InvestmentDailyEarningLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccruesFlatDailyReturnForInvestment
{
    /**
     * Persist missing flat daily return slices into escrow. Does not touch the spendable wallet.
     *
     * First earning is the calendar day after started_at (not the subscribe day).
     *
     * @return int Number of new daily earning log rows created
     */
    public function accrue(Investment $investment, ?Carbon $asOfDate = null): int
    {
        if ($investment->payout_completed_at !== null) {
            return 0;
        }

        $termDays = (int) $investment->term_days;

        if ($termDays < 1 || $investment->started_at === null) {
            return 0;
        }

        $asOf = ($asOfDate ?? now())->copy()->startOfDay();
        $startDate = $investment->started_at->copy()->startOfDay();
        // Day 1 = next calendar day after subscribe; last day = startDate + term_days.
        $firstEarningDate = $startDate->copy()->addDay();
        $lastEarningDate = $startDate->copy()->addDays($termDays);
        $expectedReturn = round((float) $investment->expected_return_amount_usd, 2);
        $flatDailyAmount = round($expectedReturn / $termDays, 2);

        return (int) DB::transaction(function () use (
            $investment,
            $asOf,
            $startDate,
            $firstEarningDate,
            $lastEarningDate,
            $termDays,
            $expectedReturn,
            $flatDailyAmount,
        ): int {
            /** @var Investment $lockedInvestment */
            $lockedInvestment = Investment::query()
                ->whereKey($investment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvestment->payout_completed_at !== null) {
                return 0;
            }

            // Drop legacy same-day-as-subscribe logs from the old rule.
            $legacySameDayDeleted = InvestmentDailyEarningLog::query()
                ->where('investment_id', $lockedInvestment->id)
                ->whereDate('earning_date', $startDate->toDateString())
                ->delete();

            if ($legacySameDayDeleted > 0) {
                $recomputedAccrued = round((float) InvestmentDailyEarningLog::query()
                    ->where('investment_id', $lockedInvestment->id)
                    ->sum('amount_usd'), 2);

                $lockedInvestment->forceFill([
                    'accrued_return_usd' => $recomputedAccrued,
                ])->save();
            }

            $existingDates = InvestmentDailyEarningLog::query()
                ->where('investment_id', $lockedInvestment->id)
                ->pluck('earning_date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all();

            $existingDateLookup = array_fill_keys($existingDates, true);
            $accruedReturnUsd = round((float) $lockedInvestment->fresh()->accrued_return_usd, 2);
            $createdCount = 0;

            for ($dayIndex = 1; $dayIndex <= $termDays; $dayIndex++) {
                $earningDate = $startDate->copy()->addDays($dayIndex);

                if ($earningDate->lessThan($firstEarningDate)) {
                    continue;
                }

                if ($earningDate->greaterThan($asOf) || $earningDate->greaterThan($lastEarningDate)) {
                    break;
                }

                $earningDateString = $earningDate->toDateString();

                if (isset($existingDateLookup[$earningDateString])) {
                    continue;
                }

                // Last term day absorbs leftover cents so logs sum to expected return exactly.
                $amountUsd = $dayIndex === $termDays
                    ? round($expectedReturn - $accruedReturnUsd, 2)
                    : $flatDailyAmount;

                if ($amountUsd < 0) {
                    $amountUsd = 0.0;
                }

                $accruedReturnUsd = round($accruedReturnUsd + $amountUsd, 2);

                InvestmentDailyEarningLog::query()->create([
                    'investment_id' => $lockedInvestment->id,
                    'earning_date' => $earningDateString,
                    'amount_usd' => $amountUsd,
                    'accrued_return_after_usd' => $accruedReturnUsd,
                    'created_at' => now(),
                ]);

                $existingDateLookup[$earningDateString] = true;
                $createdCount++;
            }

            if ($createdCount > 0 || $legacySameDayDeleted > 0) {
                $lockedInvestment->forceFill([
                    'accrued_return_usd' => $accruedReturnUsd,
                ])->save();
            }

            $investment->refresh();

            return $createdCount;
        });
    }
}
