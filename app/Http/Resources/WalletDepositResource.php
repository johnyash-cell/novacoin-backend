<?php

namespace App\Http\Resources;

use App\Enums\WalletDepositStatus;
use App\Models\WalletDeposit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin WalletDeposit
 */
class WalletDepositResource extends JsonResource
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
            'wallet_address' => $this->wallet_address,
            'platform_crypto_wallet_id' => $this->platform_crypto_wallet_id,
            'status' => $status,
            'status_label' => WalletDepositStatus::tryFrom($status)?->label()
                ?? ucfirst(str_replace('_', ' ', $status)),
            'proof_image_url' => filled($this->proof_image_path)
                ? Storage::disk('public')->url($this->proof_image_path)
                : null,
            'decline_reason' => $this->decline_reason,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
