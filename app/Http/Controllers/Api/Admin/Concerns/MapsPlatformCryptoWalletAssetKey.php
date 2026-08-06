<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Support\PlatformCryptoAssetCatalog;

trait MapsPlatformCryptoWalletAssetKey
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mapValidatedPlatformCryptoWalletPayload(array $validated): array
    {
        if (! array_key_exists('asset_key', $validated)) {
            return $validated;
        }

        $assetDefinition = PlatformCryptoAssetCatalog::definitionFor((string) $validated['asset_key']);

        $validated['asset_symbol'] = $assetDefinition['asset_symbol'];
        $validated['coingecko_asset_id'] = $assetDefinition['coingecko_asset_id'];

        if (! filled($validated['name'] ?? null)) {
            $validated['name'] = $assetDefinition['label'];
        }

        unset($validated['asset_key']);

        return $validated;
    }
}
