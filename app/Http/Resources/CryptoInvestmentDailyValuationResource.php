<?php

namespace App\Http\Resources;

use App\Models\CryptoInvestmentDailyValuation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CryptoInvestmentDailyValuation
 */
class CryptoInvestmentDailyValuationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'crypto_investment_id' => $this->crypto_investment_id,
            'valuation_date' => $this->valuation_date?->toDateString(),
            'price_usd' => $this->price_usd,
            'escrow_before_usd' => number_format((float) $this->escrow_before_usd, 2, '.', ''),
            'escrow_after_usd' => number_format((float) $this->escrow_after_usd, 2, '.', ''),
            'delta_usd' => number_format((float) $this->delta_usd, 2, '.', ''),
            'was_clamped_by_max_loss' => (bool) $this->was_clamped_by_max_loss,
            'created_at' => $this->created_at,
        ];
    }
}
