<?php

namespace App\Http\Resources;

use App\Enums\CryptoInvestmentFeeChargeSource;
use App\Enums\CryptoInvestmentFeeType;
use App\Enums\InvestmentStatus;
use App\Models\CryptoInvestment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CryptoInvestment
 */
class CryptoInvestmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storedStatus = (string) ($this->status ?? '');
        $effectiveStatus = $this->effectiveStatus();
        $feeType = (string) ($this->fee_type ?? '');
        $feeChargeSource = (string) ($this->fee_charge_source ?? '');

        return [
            'id' => $this->id,
            'coingecko_asset_id' => $this->coingecko_asset_id,
            'asset_symbol' => $this->asset_symbol,
            'asset_label' => $this->asset_label,
            'amount_usd' => $this->amount_usd,
            'fee_type' => $feeType,
            'fee_type_label' => CryptoInvestmentFeeType::tryFrom($feeType)?->label()
                ?? ucfirst(str_replace('_', ' ', $feeType)),
            'fee_value' => $this->fee_value,
            'fee_charge_source' => $feeChargeSource,
            'fee_charge_source_label' => CryptoInvestmentFeeChargeSource::tryFrom($feeChargeSource)?->label()
                ?? ucfirst(str_replace('_', ' ', $feeChargeSource)),
            'fee_usd' => $this->fee_usd,
            'committed_usd' => $this->committed_usd,
            'entry_price_usd' => $this->entry_price_usd,
            'units' => $this->units,
            'current_escrow_usd' => $this->current_escrow_usd,
            'current_price_usd' => $this->last_price_usd,
            'unrealized_pnl_usd' => $this->unrealizedPnlUsd(),
            'max_loss_enabled' => (bool) $this->max_loss_enabled,
            'max_loss_floor_usd' => $this->max_loss_floor_usd,
            'term_days' => $this->term_days,
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
        ];
    }

    private function resolveStatusLabel(string $status): string
    {
        return InvestmentStatus::tryFrom($status)?->label()
            ?? ucfirst(str_replace('_', ' ', $status));
    }
}
