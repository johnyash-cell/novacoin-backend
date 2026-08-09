<?php

namespace App\Http\Resources;

use App\Enums\InvestmentStatus;
use App\Models\CryptoInvestment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CryptoInvestment
 */
class AdminCryptoInvestmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storedStatus = (string) ($this->status ?? '');
        $effectiveStatus = $this->effectiveStatus();

        return [
            'id' => $this->id,
            'coingecko_asset_id' => $this->coingecko_asset_id,
            'asset_symbol' => $this->asset_symbol,
            'asset_label' => $this->asset_label,
            'amount_usd' => $this->amount_usd,
            'fee_usd' => $this->fee_usd,
            'committed_usd' => $this->committed_usd,
            'entry_price_usd' => $this->entry_price_usd,
            'units' => $this->units,
            'current_escrow_usd' => $this->current_escrow_usd,
            'unrealized_pnl_usd' => $this->unrealizedPnlUsd(),
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
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'first_name' => $this->user?->first_name,
                'last_name' => $this->user?->last_name,
                'email' => $this->user?->email,
            ]),
        ];
    }

    private function resolveStatusLabel(string $status): string
    {
        return InvestmentStatus::tryFrom($status)?->label()
            ?? ucfirst(str_replace('_', ' ', $status));
    }
}
