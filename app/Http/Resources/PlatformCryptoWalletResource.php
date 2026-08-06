<?php

namespace App\Http\Resources;

use App\Models\PlatformCryptoWallet;
use App\Support\PlatformCryptoAssetCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformCryptoWallet
 */
class PlatformCryptoWalletResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includeAdminFields = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'asset_symbol' => $this->asset_symbol,
            'network_name' => $this->network_name,
            'wallet_address' => $this->wallet_address,
            'is_available_for_funding' => (bool) $this->is_available_for_funding,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->includeAdminFields) {
            $payload['asset_key'] = PlatformCryptoAssetCatalog::assetKeyForCoingeckoId(
                (string) $this->coingecko_asset_id,
            );
            $payload['coingecko_asset_id'] = $this->coingecko_asset_id;
            $payload['notes'] = $this->notes;
        }

        return $payload;
    }
}
