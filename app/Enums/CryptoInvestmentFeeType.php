<?php

namespace App\Enums;

enum CryptoInvestmentFeeType: string
{
    case FixedUsd = 'fixed_usd';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::FixedUsd => 'Fixed USD',
            self::Percent => 'Percent',
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
