<?php

namespace App\Services\CryptoInvestment;

use App\Enums\CryptoInvestmentAssetPriceHistoryRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchesCoinGeckoAssetPriceHistory
{
    private const int MaxPoints = 300;

    private const int EmptyResultCacheSeconds = 45;

    /**
     * Cached CoinGecko market_chart prices for one asset + range.
     * Never throws — empty points on miss so the chart can soft-fail.
     *
     * @return list<array{t: string, price_usd: float}>
     */
    public function fetchPoints(string $coingeckoAssetId, CryptoInvestmentAssetPriceHistoryRange $range): array
    {
        $normalizedId = trim($coingeckoAssetId);

        if ($normalizedId === '') {
            return [];
        }

        $cacheKey = 'coingecko.price_history.'.$normalizedId.'.'.$range->value;

        if (Cache::has($cacheKey)) {
            /** @var list<array{t: string, price_usd: float}>|mixed $cached */
            $cached = Cache::get($cacheKey);

            return is_array($cached) ? $this->normalizeCachedPoints($cached) : [];
        }

        try {
            $points = $this->fetchLivePoints($normalizedId, $range);
        } catch (Throwable) {
            $points = [];
        }

        // Short negative cache on empty so we retry soon without hammering CG.
        $ttlSeconds = $points === []
            ? self::EmptyResultCacheSeconds
            : $range->cacheTtlSeconds();

        Cache::put($cacheKey, $points, now()->addSeconds($ttlSeconds));

        return $points;
    }

    /**
     * @return list<array{t: string, price_usd: float}>
     */
    private function fetchLivePoints(string $coingeckoAssetId, CryptoInvestmentAssetPriceHistoryRange $range): array
    {
        $request = Http::timeout(15)->connectTimeout(5)->acceptJson();

        $apiKey = config('services.coingecko.api_key');
        if (filled($apiKey)) {
            $request = $request->withHeaders([
                'x-cg-demo-api-key' => $apiKey,
            ]);
        }

        $response = $request->get(
            'https://api.coingecko.com/api/v3/coins/'.$coingeckoAssetId.'/market_chart',
            [
                'vs_currency' => 'usd',
                'days' => $range->coinGeckoDaysParameter(),
            ],
        );

        if (! $response->successful()) {
            return [];
        }

        $prices = $response->json('prices');

        if (! is_array($prices)) {
            return [];
        }

        $points = [];

        foreach ($prices as $sample) {
            if (! is_array($sample) || count($sample) < 2) {
                continue;
            }

            $timestampMs = $sample[0] ?? null;
            $price = $sample[1] ?? null;

            if (! is_numeric($timestampMs) || ! is_numeric($price)) {
                continue;
            }

            $priceUsd = (float) $price;

            if (! is_finite($priceUsd) || $priceUsd < 0) {
                continue;
            }

            $points[] = [
                't' => Carbon::createFromTimestampMs((int) $timestampMs)->utc()->toISOString(),
                'price_usd' => $priceUsd,
            ];
        }

        return $this->capPointCount($points);
    }

    /**
     * @param  list<array{t: string, price_usd: float}>  $points
     * @return list<array{t: string, price_usd: float}>
     */
    private function capPointCount(array $points): array
    {
        $count = count($points);

        if ($count <= self::MaxPoints) {
            return $points;
        }

        $capped = [];
        $lastIndex = $count - 1;

        for ($i = 0; $i < self::MaxPoints; $i++) {
            $sourceIndex = (int) round(($i / (self::MaxPoints - 1)) * $lastIndex);
            $capped[] = $points[$sourceIndex];
        }

        return $capped;
    }

    /**
     * @param  array<int, mixed>  $cached
     * @return list<array{t: string, price_usd: float}>
     */
    private function normalizeCachedPoints(array $cached): array
    {
        $points = [];

        foreach ($cached as $row) {
            if (! is_array($row)) {
                continue;
            }

            $t = $row['t'] ?? null;
            $price = $row['price_usd'] ?? null;

            if (! is_string($t) || $t === '' || ! is_numeric($price)) {
                continue;
            }

            $priceUsd = (float) $price;

            if (! is_finite($priceUsd) || $priceUsd < 0) {
                continue;
            }

            $points[] = [
                't' => $t,
                'price_usd' => $priceUsd,
            ];
        }

        return $points;
    }
}
