<?php

namespace App\Http\Resources;

use App\Models\InvestmentDailyEarningLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvestmentDailyEarningLog
 */
class InvestmentDailyEarningLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'investment_id' => $this->investment_id,
            'earning_date' => $this->earning_date?->toDateString(),
            'amount_usd' => number_format((float) $this->amount_usd, 2, '.', ''),
            'accrued_return_after_usd' => number_format((float) $this->accrued_return_after_usd, 2, '.', ''),
            'created_at' => $this->created_at,
        ];
    }
}
