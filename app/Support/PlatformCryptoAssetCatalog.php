<?php

namespace App\Support;

use InvalidArgumentException;

class PlatformCryptoAssetCatalog
{
    /**
     * Friendly assets admins may pick. CoinGecko ids stay internal.
     *
     * @return array<string, array{label: string, asset_symbol: string, coingecko_asset_id: string}>
     */
    public static function definitions(): array
    {
        return [
            'bitcoin' => [
                'label' => 'Bitcoin',
                'asset_symbol' => 'BTC',
                'coingecko_asset_id' => 'bitcoin',
            ],
            'ethereum' => [
                'label' => 'Ethereum',
                'asset_symbol' => 'ETH',
                'coingecko_asset_id' => 'ethereum',
            ],
            'tether' => [
                'label' => 'USDT',
                'asset_symbol' => 'USDT',
                'coingecko_asset_id' => 'tether',
            ],
            'usd-coin' => [
                'label' => 'USDC',
                'asset_symbol' => 'USDC',
                'coingecko_asset_id' => 'usd-coin',
            ],
            'binancecoin' => [
                'label' => 'BNB',
                'asset_symbol' => 'BNB',
                'coingecko_asset_id' => 'binancecoin',
            ],
            'solana' => [
                'label' => 'Solana',
                'asset_symbol' => 'SOL',
                'coingecko_asset_id' => 'solana',
            ],
            'ripple' => [
                'label' => 'XRP',
                'asset_symbol' => 'XRP',
                'coingecko_asset_id' => 'ripple',
            ],
            'litecoin' => [
                'label' => 'Litecoin',
                'asset_symbol' => 'LTC',
                'coingecko_asset_id' => 'litecoin',
            ],
            'dogecoin' => [
                'label' => 'Dogecoin',
                'asset_symbol' => 'DOGE',
                'coingecko_asset_id' => 'dogecoin',
            ],
            'tron' => [
                'label' => 'TRON',
                'asset_symbol' => 'TRX',
                'coingecko_asset_id' => 'tron',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return list<array{value: string, label: string, asset_symbol: string}>
     */
    public static function optionsForAdminSelect(): array
    {
        $options = [];

        foreach (self::definitions() as $assetKey => $definition) {
            $options[] = [
                'value' => $assetKey,
                'label' => $definition['label'],
                'asset_symbol' => $definition['asset_symbol'],
            ];
        }

        return $options;
    }

    /**
     * @return array{label: string, asset_symbol: string, coingecko_asset_id: string}
     */
    public static function definitionFor(string $assetKey): array
    {
        $definitions = self::definitions();

        if (! array_key_exists($assetKey, $definitions)) {
            throw new InvalidArgumentException('Unknown platform crypto asset key.');
        }

        return $definitions[$assetKey];
    }

    public static function assetKeyForCoingeckoId(string $coingeckoAssetId): ?string
    {
        foreach (self::definitions() as $assetKey => $definition) {
            if ($definition['coingecko_asset_id'] === $coingeckoAssetId) {
                return $assetKey;
            }
        }

        return null;
    }
}
