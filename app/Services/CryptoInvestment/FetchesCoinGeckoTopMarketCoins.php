<?php

namespace App\Services\CryptoInvestment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FetchesCoinGeckoTopMarketCoins
{
    /**
     * Live CoinGecko top 30 by market cap for admin coin picker.
     *
     * @return list<array{value: string, label: string, asset_symbol: string}>
     */
    public function fetchTopThirty(): array
    {
        return Cache::remember('coingecko.top_market_coins.30', now()->addMinutes(5), function (): array {
            $request = Http::timeout(15)->acceptJson();

            $apiKey = config('services.coingecko.api_key');
            if (filled($apiKey)) {
                $request = $request->withHeaders([
                    'x-cg-demo-api-key' => $apiKey,
                ]);
            }

            $response = $request->get('https://api.coingecko.com/api/v3/coins/markets', [
                'vs_currency' => 'usd',
                'order' => 'market_cap_desc',
                'per_page' => 30,
                'page' => 1,
                'sparkline' => 'false',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to fetch top market coins. Please try again shortly.');
            }

            $markets = $response->json();

            if (! is_array($markets) || $markets === []) {
                throw new RuntimeException('Top market coin list is empty right now.');
            }

            $options = [];

            foreach ($markets as $market) {
                if (! is_array($market)) {
                    continue;
                }

                $id = isset($market['id']) ? (string) $market['id'] : '';
                $symbol = isset($market['symbol']) ? strtoupper((string) $market['symbol']) : '';
                $name = isset($market['name']) ? (string) $market['name'] : '';

                if ($id === '' || $symbol === '' || $name === '') {
                    continue;
                }

                $options[] = [
                    'value' => $id,
                    'label' => $name.' ('.$symbol.')',
                    'asset_symbol' => $symbol,
                ];
            }

            if ($options === []) {
                throw new RuntimeException('Top market coin list could not be parsed.');
            }

            return $options;
        });
    }

    /**
     * Resolve display fields for a CoinGecko id from the cached top-30 list.
     *
     * @return array{coingecko_asset_id: string, asset_symbol: string, asset_label: string}|null
     */
    public function findTopThirtyCoinById(string $coingeckoAssetId): ?array
    {
        foreach ($this->fetchTopThirty() as $option) {
            if ($option['value'] === $coingeckoAssetId) {
                $label = $option['label'];
                $parenPos = strrpos($label, ' (');
                $assetLabel = $parenPos !== false ? substr($label, 0, $parenPos) : $label;

                return [
                    'coingecko_asset_id' => $option['value'],
                    'asset_symbol' => $option['asset_symbol'],
                    'asset_label' => $assetLabel,
                ];
            }
        }

        return null;
    }
}
