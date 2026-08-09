<?php

namespace App\Http\Resources;

use App\Enums\InvestmentStatus;
use App\Models\Investment;
use App\Models\InvestmentDailyEarningLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Investment
 */
class InvestmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storedStatus = (string) ($this->status ?? '');
        $effectiveStatus = $this->effectiveStatus();
        $accruedReturnUsd = number_format((float) ($this->accrued_return_usd ?? 0), 2, '.', '');
        $todayEarningUsd = $this->resolveTodayEarningUsd();

        return [
            'id' => $this->id,
            'investment_package_id' => $this->investment_package_id,
            'package_name' => $this->package_name,
            'amount_usd' => $this->amount_usd,
            'expected_return_percent' => $this->expected_return_percent,
            'term_days' => $this->term_days,
            'expected_return_amount_usd' => $this->expected_return_amount_usd,
            'expected_payout_amount_usd' => $this->expected_payout_amount_usd,
            'accrued_return_usd' => $accruedReturnUsd,
            'today_earning_usd' => $todayEarningUsd,
            'total_earned_return_usd' => $accruedReturnUsd,
            'status' => $storedStatus,
            'status_label' => $this->resolveStatusLabel($storedStatus),
            'effective_status' => $effectiveStatus,
            'effective_status_label' => $this->resolveStatusLabel($effectiveStatus),
            'started_at' => $this->started_at,
            'matures_at' => $this->matures_at,
            'ended_at' => $this->ended_at,
            'payout_completed_at' => $this->payout_completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'investment_package' => $this->whenLoaded(
                'investmentPackage',
                fn () => (new MemberInvestmentPackageResource($this->investmentPackage))->resolve(),
            ),
        ];
    }

    private function resolveTodayEarningUsd(): string
    {
        if ($this->relationLoaded('dailyEarningLogs')) {
            $todayLog = $this->dailyEarningLogs->first(
                fn (InvestmentDailyEarningLog $log): bool => $log->earning_date?->isToday() ?? false,
            );

            return number_format((float) ($todayLog?->amount_usd ?? 0), 2, '.', '');
        }

        $todayAmount = InvestmentDailyEarningLog::query()
            ->where('investment_id', $this->id)
            ->whereDate('earning_date', now()->toDateString())
            ->value('amount_usd');

        return number_format((float) ($todayAmount ?? 0), 2, '.', '');
    }

    private function resolveStatusLabel(string $status): string
    {
        return InvestmentStatus::tryFrom($status)?->label()
            ?? ucfirst(str_replace('_', ' ', $status));
    }
}
