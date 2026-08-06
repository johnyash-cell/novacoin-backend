<?php

namespace App\Http\Resources;

use App\Enums\WalletWithdrawalStatus;
use App\Models\WalletWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletWithdrawal
 */
class WalletWithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = (string) ($this->status ?? '');

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'usd_amount' => (float) $this->usd_amount,
            'crypto_amount_expected' => (float) $this->crypto_amount_expected,
            'conversion_rate_usd_per_unit' => (float) $this->conversion_rate_usd_per_unit,
            'quoted_at' => $this->quoted_at,
            'asset_symbol' => $this->asset_symbol,
            'network_name' => $this->network_name,
            'platform_crypto_wallet_id' => $this->platform_crypto_wallet_id,
            'destination_wallet_address' => $this->destination_wallet_address,
            'status' => $status,
            'status_label' => WalletWithdrawalStatus::tryFrom($status)?->label()
                ?? ucfirst(str_replace('_', ' ', $status)),
            'decline_reason' => $this->decline_reason,
            'outbound_transaction_reference' => $this->outbound_transaction_reference,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
