<?php

namespace App\Services\CryptoInvestment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchesCoinGeckoMarketSnapshotsForAssetIds
{
    /**
     * Batch CoinGecko /coins/markets for supported asset ids (cached ~5 minutes).
     * Never throws — empty map on failure so the catalog can still respond.
     *
     * @param  list<string>  $coingeckoAssetIds
     * @return array<string, array{
     *     current_price_usd: float|null,
     *     price_change_percentage_24h: float|null,
     *     image_url: string|null
     * }>
     */
    public function fetchByAssetIds(array $coingeckoAssetIds): array
    {
        $normalizedIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): string => trim((string) $id), $coingeckoAssetIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($normalizedIds === []) {
            return [];
        }

        sort($normalizedIds);

        $cacheKey = 'coingecko.market_snapshots.'.md5(implode(',', $normalizedIds));

        /** @var array<string, array{current_price_usd: float|null, price_change_percentage_24h: float|null, image_url: string|null}>|null $cached */
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $this->warmUsdPriceCacheFromSnapshots($cached);

            return $cached;
        }

        try {
            $snapshots = $this->fetchLiveMarketSnapshots($normalizedIds);
        } catch (Throwable) {
            return [];
        }

        // Do not cache empty failures — retry next request when CoinGecko recovers.
        if ($snapshots !== []) {
            Cache::put($cacheKey, $snapshots, now()->addMinutes(5));
            $this->warmUsdPriceCacheFromSnapshots($snapshots);
        }

        return $snapshots;
    }

    /**
     * @param  list<string>  $normalizedIds
     * @return array<string, array{current_price_usd: float|null, price_change_percentage_24h: float|null, image_url: string|null}>
     */
    private function fetchLiveMarketSnapshots(array $normalizedIds): array
    {
        $request = Http::timeout(15)->connectTimeout(5)->acceptJson();

        $apiKey = config('services.coingecko.api_key');
        if (filled($apiKey)) {
            $request = $request->withHeaders([
                'x-cg-demo-api-key' => $apiKey,
            ]);
        }

        $response = $request->get('https://api.coingecko.com/api/v3/coins/markets', [
            'vs_currency' => 'usd',
            'ids' => implode(',', $normalizedIds),
            'order' => 'market_cap_desc',
            'per_page' => max(1, count($normalizedIds)),
            'page' => 1,
            'sparkline' => 'false',
            'price_change_percentage' => '24h',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $markets = $response->json();

        if (! is_array($markets)) {
            return [];
        }

        $snapshots = [];

        foreach ($markets as $market) {
            if (! is_array($market)) {
                continue;
            }

            $id = isset($market['id']) ? (string) $market['id'] : '';

            if ($id === '') {
                continue;
            }

            $currentPrice = $market['current_price'] ?? null;
            $change24h = $market['price_change_percentage_24h'] ?? null;
            $image = $market['image'] ?? null;

            $snapshots[$id] = [
                'current_price_usd' => is_numeric($currentPrice) && (float) $currentPrice > 0
                    ? (float) $currentPrice
                    : null,
                'price_change_percentage_24h' => is_numeric($change24h)
                    ? (float) $change24h
                    : null,
                'image_url' => is_string($image) && $image !== ''
                    ? $image
                    : null,
            ];
        }

        return $snapshots;
    }

    /**
     * Align quote/invest simple-price cache with the markets batch for this window.
     *
     * @param  array<string, array{current_price_usd: float|null, price_change_percentage_24h: float|null, image_url: string|null}>  $snapshots
     */
    private function warmUsdPriceCacheFromSnapshots(array $snapshots): void
    {
        foreach ($snapshots as $coingeckoAssetId => $snapshot) {
            $price = $snapshot['current_price_usd'] ?? null;

            if ($price === null || $price <= 0) {
                continue;
            }

            Cache::put('coingecko.usd_price.'.$coingeckoAssetId, $price, now()->addSeconds(60));
        }
    }
}
