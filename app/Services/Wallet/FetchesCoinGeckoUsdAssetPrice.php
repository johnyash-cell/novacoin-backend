<?php

namespace App\Services\Wallet;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FetchesCoinGeckoUsdAssetPrice
{
    public function fetchUsdPricePerUnit(string $coingeckoAssetId): float
    {
        $cacheKey = 'coingecko.usd_price.'.$coingeckoAssetId;

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($coingeckoAssetId): float {
            $request = Http::timeout(10)->acceptJson();

            $apiKey = config('services.coingecko.api_key');
            if (filled($apiKey)) {
                $request = $request->withHeaders([
                    'x-cg-demo-api-key' => $apiKey,
                ]);
            }

            $response = $request->get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => $coingeckoAssetId,
                'vs_currencies' => 'usd',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to fetch live crypto price. Please try again shortly.');
            }

            $usdPrice = $response->json($coingeckoAssetId.'.usd');

            if (! is_numeric($usdPrice) || (float) $usdPrice <= 0) {
                throw new RuntimeException('Live crypto price is unavailable for this asset.');
            }

            return (float) $usdPrice;
        });
    }
}
