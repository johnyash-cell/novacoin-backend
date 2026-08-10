<?php

namespace App\Enums;

enum CryptoInvestmentAssetPriceHistoryRange: string
{
    case Hours24 = '24h';
    case Days7 = '7d';
    case Days30 = '30d';
    case Year1 = '1y';

    /**
     * CoinGecko `days` query for /coins/{id}/market_chart.
     */
    public function coinGeckoDaysParameter(): int
    {
        return match ($this) {
            self::Hours24 => 1,
            self::Days7 => 7,
            self::Days30 => 30,
            self::Year1 => 365,
        };
    }

    public function cacheTtlSeconds(): int
    {
        return match ($this) {
            self::Hours24, self::Days7 => 300,
            self::Days30, self::Year1 => 1800,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
